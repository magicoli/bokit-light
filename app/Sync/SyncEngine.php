<?php

namespace App\Sync;

use App\Sync\Contracts\PushableConnector;
use App\Sync\Contracts\SourceConnector;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\Unit;
use App\Support\NormalizedBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Central synchronization engine.
 *
 * Connectors fetch and normalize bookings; this engine owns all database
 * logic: matching, ownership, persistence and vanished detection.
 *
 * Matching priority for each incoming booking:
 *   1. (source_key, external_id) pair in booking_sources — authoritative:
 *      once stored, the id wins even if dates, guest or unit changed.
 *   2. Origin hint: a reference (or legacy uid) matching the origin the
 *      source declared (e.g. Beds24 'referer' = iCal Import).
 *   3. Same-type reference: another feed of the same type already saw this
 *      external id (iCal UIDs are globally unique across feeds).
 *   4. Legacy uid stored in bookings.uid (transitional).
 *   5. Heuristics: unit + exact dates + email, then unit + exact dates + guest name.
 *      A heuristic match always stores the (source_key, external_id) pair so
 *      the next run matches by id.
 *
 * Ownership: only one source syncs a booking — the origin when one of the
 * connected sources reliably claims it (claimsOrigin), otherwise the first
 * source that found the booking (oldest real reference). All other sources
 * are recorded in booking_sources for information only and never modify the
 * booking. Manual bookings are never owned by any source.
 */
class SyncEngine
{
    private const CANCELLED_STATUSES = Booking::CANCELLED_STATUSES;

    private const PLACEHOLDER_GUEST_NAMES = ['Guest', 'Unknown Guest', ''];

    /**
     * Sync one source entry of one unit.
     *
     * @return array{label: string, success: bool, total: int, new: int, updated: int, deleted: int, vanished: int, error: ?string}
     */
    public function sync(Unit $unit, array $sourceConfig, SourceConnector $connector, bool $dryRun = false): array
    {
        $label = $connector->displayLabel($sourceConfig);

        try {
            $incoming = $connector->fetchBookings($unit, $sourceConfig);
        } catch (\Throwable $e) {
            return [
                'label' => $label,
                'success' => false,
                'total' => 0,
                'new' => 0,
                'updated' => 0,
                'deleted' => 0,
                'vanished' => 0,
                'error' => $e->getMessage(),
            ];
        }

        $sourceType = $connector->sourceType();
        $sourceKey = $connector->sourceKey($unit, $sourceConfig);

        $stats = [
            'label' => $label,
            'success' => true,
            'total' => 0,
            'new' => 0,
            'updated' => 0,
            'deleted' => 0,
            'vanished' => 0,
            'error' => null,
        ];

        $seenIds = [];

        foreach ($incoming as $normalized) {
            $seenIds[] = $normalized->externalId;
            $stats['total']++;

            if (in_array($normalized->status, self::CANCELLED_STATUSES, true)) {
                $stats['deleted']++;
            }

            $booking = $this->match($unit, $sourceType, $sourceKey, $normalized);

            if ($dryRun) {
                if (! $booking) {
                    $stats['new']++;
                }

                continue;
            }

            if (! $booking) {
                $this->createBooking($unit, $sourceType, $sourceKey, $normalized);
                $stats['new']++;

                continue;
            }

            $reference = $this->ensureReference($booking, $sourceType, $sourceKey, $normalized);

            if ($this->applyUpdate($booking, $reference, $normalized)) {
                $stats['updated']++;
            }
        }

        if (! $dryRun) {
            $this->handleVanished($unit, $sourceKey, $seenIds, $stats);
        }

        return $stats;
    }

    /**
     * Push bokit-origin (manual) bookings of this unit to a source that
     * accepts them. Only current and future bookings are considered;
     * cancellations of already-pushed bookings are propagated. After a
     * creation the (source_key, external_id) pair is written immediately,
     * so the next pull recognizes our own booking instead of duplicating
     * it (echo suppression).
     *
     * @return array{label: string, success: bool, created: int, updated: int, failed: int, error: ?string}
     */
    public function pushBookings(Unit $unit, array $sourceConfig, PushableConnector&SourceConnector $connector, bool $dryRun = false): array
    {
        $sourceKey = $connector->sourceKey($unit, $sourceConfig);
        $sourceType = $connector->sourceType();

        $stats = [
            'label' => $connector->displayLabel($sourceConfig).' push',
            'success' => true,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'error' => null,
        ];

        $bookings = Booking::withTrashed()
            ->where('unit_id', $unit->id)
            ->where('is_manual', true)
            ->whereDate('check_out', '>=', now()->format('Y-m-d'))
            ->get();

        foreach ($bookings as $booking) {
            try {
                $result = $this->pushToSource($booking, $unit, $sourceConfig, $connector, $dryRun);
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['error'] = $e->getMessage();
                Log::warning("[SyncEngine] Push failed for booking #{$booking->id} to {$sourceKey}: {$e->getMessage()}");

                continue;
            }

            if ($result === 'created') {
                $stats['created']++;
            } elseif ($result === 'updated') {
                $stats['updated']++;
            }
        }

        $stats['success'] = $stats['failed'] === 0;

        return $stats;
    }

    /**
     * Push one booking to every writable source of its unit — the on-save
     * path for bokit-as-master edits. Protected (self-managed OTA) bookings
     * are never pushed: their dates and price are owned by the OTA.
     *
     * @return array{pushed: int, failed: int, errors: array<int, string>}
     */
    public function pushBooking(Booking $booking, bool $dryRun = false): array
    {
        $stats = ['pushed' => 0, 'failed' => 0, 'errors' => []];

        $unit = $booking->unit;

        if (! $unit || $booking->isProtected()) {
            return $stats;
        }

        $registry = app(SyncRegistry::class);

        foreach ($unit->options['sources'] ?? [] as $sourceConfig) {
            if (! ($sourceConfig['enabled'] ?? true) || ($sourceConfig['readonly'] ?? false)) {
                continue;
            }

            $connector = $registry->getForType($sourceConfig['type'] ?? '');

            if (! ($connector instanceof PushableConnector && $connector instanceof SourceConnector)) {
                continue;
            }

            try {
                if ($this->pushToSource($booking, $unit, $sourceConfig, $connector, $dryRun) !== 'skipped') {
                    $stats['pushed']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = $e->getMessage();
                Log::warning("[SyncEngine] Push-on-save failed for booking #{$booking->id}: {$e->getMessage()}");
            }
        }

        return $stats;
    }

    /**
     * Push one booking to one source: create or update its external
     * booking and record the (source_key, external_id) pair with pushed_at
     * for echo suppression. Returns 'created', 'updated' or 'skipped'.
     *
     * @throws \Throwable on API failure
     */
    private function pushToSource(Booking $booking, Unit $unit, array $sourceConfig, PushableConnector&SourceConnector $connector, bool $dryRun): string
    {
        $sourceKey = $connector->sourceKey($unit, $sourceConfig);
        $sourceType = $connector->sourceType();

        $reference = $booking->sources()->where('source_key', $sourceKey)->first();

        if (! $reference) {
            // A booking never pushed and already cancelled blocks nothing.
            if ($booking->isCancelled()) {
                return 'skipped';
            }
            if ($dryRun) {
                return 'created';
            }

            $externalId = $connector->pushBooking($unit, $sourceConfig, $booking, null);
            $this->writeReference($booking, [
                'source_type' => $sourceType,
                'source_key' => $sourceKey,
                'external_id' => $externalId,
                'is_origin' => false,
                'is_placeholder' => false,
                'last_seen_at' => now(),
                'pushed_at' => now(),
            ]);
            Log::info("[SyncEngine] Pushed booking #{$booking->id} to {$sourceKey} as {$externalId}");

            return 'created';
        }

        $needsPush = $reference->pushed_at === null
            || $booking->updated_at?->greaterThan($reference->pushed_at)
            || ($booking->trashed() && $booking->deleted_at?->greaterThan($reference->pushed_at));

        if (! $needsPush) {
            return 'skipped';
        }
        if ($dryRun) {
            return 'updated';
        }

        $connector->pushBooking($unit, $sourceConfig, $booking, $reference->external_id);
        $reference->update(['pushed_at' => now(), 'last_seen_at' => now()]);
        Log::info("[SyncEngine] Pushed update of booking #{$booking->id} to {$sourceKey} ({$reference->external_id})");

        return 'updated';
    }

    /**
     * Find the booking this normalized booking refers to, or null when new.
     */
    private function match(Unit $unit, string $sourceType, string $sourceKey, NormalizedBooking $normalized): ?Booking
    {
        // 1. Authoritative pair — wins even if dates/guest/unit changed at source.
        $reference = BookingSource::query()
            ->where('source_key', $sourceKey)
            ->where('external_id', $normalized->externalId)
            ->first();

        if ($reference) {
            $booking = $reference->booking;

            if ($booking) {
                return $booking;
            }

            // Orphaned reference (booking deleted) — clean up and keep matching.
            $reference->delete();
        }

        // 2. Origin hint declared by the source.
        if ($normalized->originHint) {
            $booking = $this->findByReference(
                $unit,
                $normalized->originHint['type'],
                $normalized->originHint['external_id'],
            );

            if ($this->accept($booking, $sourceKey, $normalized)) {
                return $booking;
            }

            $booking = Booking::query()
                ->where('unit_id', $unit->id)
                ->where('uid', $normalized->originHint['external_id'])
                ->first();

            if ($this->accept($booking, $sourceKey, $normalized)) {
                return $booking;
            }
        }

        // 3. Same external id seen by another source of the same type.
        $booking = $this->findByReference($unit, $sourceType, $normalized->externalId);

        if ($this->accept($booking, $sourceKey, $normalized)) {
            return $booking;
        }

        // 4. Legacy uid (transitional, pre-booking_sources data).
        if ($normalized->legacyUid) {
            $booking = Booking::query()
                ->where('unit_id', $unit->id)
                ->where('uid', $normalized->legacyUid)
                ->first();

            if ($this->accept($booking, $sourceKey, $normalized)) {
                return $booking;
            }
        }

        // 5. Heuristics: exact dates + email, then exact dates + guest name.
        if ($normalized->email) {
            $booking = Booking::query()
                ->where('unit_id', $unit->id)
                ->whereDate('check_in', $normalized->checkIn)
                ->whereDate('check_out', $normalized->checkOut)
                ->whereRaw("JSON_EXTRACT(metadata, '$.email') = ?", [$normalized->email])
                ->first();

            if ($this->accept($booking, $sourceKey, $normalized)) {
                return $booking;
            }
        }

        if (! in_array($normalized->guestName, self::PLACEHOLDER_GUEST_NAMES, true)) {
            $booking = Booking::query()
                ->where('unit_id', $unit->id)
                ->whereDate('check_in', $normalized->checkIn)
                ->whereDate('check_out', $normalized->checkOut)
                ->where('guest_name', $normalized->guestName)
                ->first();

            if ($this->accept($booking, $sourceKey, $normalized)) {
                return $booking;
            }
        }

        return null;
    }

    /**
     * A candidate found by hint, uid or heuristics is only acceptable if
     * the same source does not already know it under another external id:
     * one source can never report two of its bookings as a single one
     * (e.g. a cancelled booking and its replacement on the same dates must
     * stay separate bookings).
     */
    private function accept(?Booking $booking, string $sourceKey, NormalizedBooking $normalized): bool
    {
        if (! $booking) {
            return false;
        }

        $conflict = $booking->sources()
            ->where('source_key', $sourceKey)
            ->where('external_id', '!=', $normalized->externalId)
            ->exists();

        if ($conflict) {
            Log::info("[SyncEngine] Refusing match: booking #{$booking->id} already referenced by {$sourceKey} under another id (incoming {$normalized->externalId})");
        }

        return ! $conflict;
    }

    private function findByReference(Unit $unit, string $sourceType, string $externalId): ?Booking
    {
        return Booking::query()
            ->where('unit_id', $unit->id)
            ->whereHas('sources', function ($query) use ($sourceType, $externalId) {
                $query->where('source_type', $sourceType)->where('external_id', $externalId);
            })
            ->first();
    }

    private function createBooking(Unit $unit, string $sourceType, string $sourceKey, NormalizedBooking $normalized): void
    {
        $metadata = $normalized->metadata;

        if ($normalized->email) {
            $metadata['email'] = $normalized->email;
        }

        $booking = new Booking([
            'unit_id' => $unit->id,
            'property_id' => $unit->property_id,
            'uid' => $normalized->legacyUid ?? "{$sourceKey}-{$normalized->externalId}",
            'check_in' => $normalized->checkIn,
            'check_out' => $normalized->checkOut,
            'guest_name' => $normalized->guestName,
            'status' => $normalized->status,
            'price' => $normalized->price,
            'commission' => $normalized->commission,
            'guests' => $normalized->guests,
            'adults' => $normalized->adults,
            'children' => $normalized->children,
            'source_name' => $normalized->channel,
            'group_id' => $normalized->groupId,
            'is_manual' => false,
            'metadata' => $metadata,
        ]);

        // Timestamps mirror the reservation's life in the source system,
        // not the moment our sync first saw it. Explicitly assigned values
        // are kept by Eloquent (updateTimestamps skips dirty attributes).
        if ($normalized->sourceCreatedAt) {
            $booking->created_at = Carbon::parse($normalized->sourceCreatedAt);
        }
        if ($normalized->sourceUpdatedAt) {
            $booking->updated_at = Carbon::parse($normalized->sourceUpdatedAt);
        }

        $booking->save();

        $this->writeReference($booking, [
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
            'external_id' => $normalized->externalId,
            'is_origin' => $normalized->claimsOrigin,
            'is_placeholder' => false,
            'last_seen_at' => now(),
        ]);

        if ($normalized->originHint) {
            // The true origin isn't connected yet — record it as a placeholder
            // so the real source can claim it later.
            $this->writeReference($booking, [
                'source_type' => $normalized->originHint['type'],
                'source_key' => $normalized->originHint['type'],
                'external_id' => $normalized->originHint['external_id'],
                'is_origin' => true,
                'is_placeholder' => true,
                'last_seen_at' => null,
            ]);
        }
    }

    /**
     * Upsert a reference on its unique (source_key, external_id) pair.
     * A leftover row — e.g. orphaned by a manual wipe of the bookings table,
     * where SQLite's CLI does not run FK cascades — is reattached to the new
     * booking instead of blowing up the unique constraint.
     *
     * @param  array<string,mixed>  $attributes
     */
    private function writeReference(Booking $booking, array $attributes): BookingSource
    {
        return BookingSource::updateOrCreate(
            [
                'source_key' => $attributes['source_key'],
                'external_id' => $attributes['external_id'],
            ],
            [...$attributes, 'booking_id' => $booking->id],
        );
    }

    /**
     * Make sure the booking carries a reference for this source, creating or
     * claiming a placeholder as needed. A source that reliably claims origin
     * takes ownership unless another real source already holds it.
     * Always refreshes last_seen_at.
     */
    private function ensureReference(Booking $booking, string $sourceType, string $sourceKey, NormalizedBooking $normalized): BookingSource
    {
        $canClaim = $normalized->claimsOrigin && ! $booking->is_manual;

        $reference = $booking->sources()
            ->where('source_key', $sourceKey)
            ->where('external_id', $normalized->externalId)
            ->first();

        if (! $reference) {
            // Take over a placeholder created from an origin hint for this
            // exact id — whatever source type the hint guessed: a Beds24
            // 'iCal Import' referer types its placeholder as ical even when
            // the actual origin later connects as hbook.
            $reference = $booking->sources()
                ->where('is_placeholder', true)
                ->where('external_id', $normalized->externalId)
                ->first();

            if ($reference) {
                $reference->update([
                    'source_type' => $sourceType,
                    'source_key' => $sourceKey,
                    'is_placeholder' => false,
                    'is_origin' => $canClaim,
                ]);
            }
        }

        $hasClaimedOrigin = fn (): bool => $booking->sources()
            ->where('is_origin', true)
            ->where('is_placeholder', false)
            ->exists();

        if (! $reference) {
            $reference = $this->writeReference($booking, [
                'source_type' => $sourceType,
                'source_key' => $sourceKey,
                'external_id' => $normalized->externalId,
                'is_origin' => $canClaim && ! $hasClaimedOrigin(),
                'is_placeholder' => false,
            ]);
        } elseif ($canClaim && ! $reference->is_origin && ! $hasClaimedOrigin()) {
            $reference->update(['is_origin' => true]);
        }

        // Once a real origin is connected, placeholder guesses pointing at
        // unconnected sources are resolved — they no longer flag origin.
        if ($reference->is_origin && ! $reference->is_placeholder) {
            $booking->sources()
                ->whereKeyNot($reference->id)
                ->where('is_placeholder', true)
                ->where('is_origin', true)
                ->update(['is_origin' => false]);
        }

        $reference->update(['last_seen_at' => now()]);

        return $reference;
    }

    /**
     * True when this reference is allowed to perform a full update:
     * it is the claimed origin, or no real origin is connected and this is
     * the booking's oldest non-placeholder reference. Placeholder origins
     * (hints pointing to a source that isn't connected) never act.
     */
    private function isActingOrigin(Booking $booking, BookingSource $reference): bool
    {
        if ($booking->is_manual) {
            return false;
        }

        $claimedOrigin = $booking->sources()
            ->where('is_origin', true)
            ->where('is_placeholder', false)
            ->first();

        if ($claimedOrigin) {
            return $claimedOrigin->id === $reference->id;
        }

        $oldestReal = $booking->sources()
            ->where('is_placeholder', false)
            ->orderBy('id')
            ->first();

        return $oldestReal !== null && $oldestReal->id === $reference->id;
    }

    /**
     * Apply changes from the source, respecting ownership. Only the acting
     * origin syncs the booking — other sources are recorded for information
     * and never modify anything.
     *
     * @return bool whether anything was updated
     */
    private function applyUpdate(Booking $booking, BookingSource $reference, NormalizedBooking $normalized): bool
    {
        if (! $this->isActingOrigin($booking, $reference)) {
            return false;
        }

        $changes = $this->fullChanges($booking, $normalized);

        if (empty($changes)) {
            return false;
        }

        // forceFill: created_at/updated_at are not fillable, update() would
        // silently drop them and touch now() instead of the source's dates.
        $booking->forceFill($changes);
        $booking->save();

        return true;
    }

    /**
     * Full diff for the acting origin. Only fields the source actually
     * provides are compared; absent data never erases existing values.
     *
     * @return array<string,mixed>
     */
    private function fullChanges(Booking $booking, NormalizedBooking $normalized): array
    {
        $changes = [];

        if ($booking->check_in->format('Y-m-d') !== $normalized->checkIn) {
            $changes['check_in'] = $normalized->checkIn;
        }

        if ($booking->check_out->format('Y-m-d') !== $normalized->checkOut) {
            $changes['check_out'] = $normalized->checkOut;
        }

        if (! in_array($normalized->guestName, self::PLACEHOLDER_GUEST_NAMES, true)
            && $booking->guest_name !== $normalized->guestName) {
            $changes['guest_name'] = $normalized->guestName;
        }

        // Never downgrade confirmed → option, never overwrite with undefined.
        if ($normalized->status !== 'undefined'
            && $booking->status !== $normalized->status
            && ! ($booking->status === 'confirmed' && $normalized->status === 'option')) {
            $changes['status'] = $normalized->status;
        }

        // The acting origin (fullChanges only runs for it) may set the price
        // to any definitive value it reports — including 0 (e.g. a Beds24
        // invoice zeroed by the user). null means "no price information" and
        // never touches the stored value, except to clear a group member.
        if ($normalized->price !== null && (float) $booking->getRawOriginal('price') !== $normalized->price) {
            $changes['price'] = $normalized->price;
        } elseif ($normalized->price === null
            && $normalized->groupId !== null
            && $normalized->claimsOrigin
            && $booking->getRawOriginal('price') !== null) {
            // A group member reported priceless by its claiming origin must
            // not keep a stale price from another source: the group total
            // lives on the carrier member, a leftover here would double the
            // group sum.
            $changes['price'] = null;
        }

        if ($normalized->commission !== null && (float) $booking->getRawOriginal('commission') !== $normalized->commission) {
            $changes['commission'] = $normalized->commission;
        }

        if ($normalized->guests !== null && $booking->getRawOriginal('guests') !== $normalized->guests) {
            $changes['guests'] = $normalized->guests;
        }

        if ($normalized->adults !== null && $booking->getRawOriginal('adults') !== $normalized->adults) {
            $changes['adults'] = $normalized->adults;
        }

        if ($normalized->children !== null && $booking->getRawOriginal('children') !== $normalized->children) {
            $changes['children'] = $normalized->children;
        }

        if ($normalized->channel !== null && $booking->getRawOriginal('source_name') !== $normalized->channel) {
            $changes['source_name'] = $normalized->channel;
        }

        if ($normalized->groupId !== null && (string) $booking->getRawOriginal('group_id') !== $normalized->groupId) {
            $changes['group_id'] = $normalized->groupId;
        }

        if ($normalized->sourceCreatedAt
            && ($booking->created_at === null || ! Carbon::parse($normalized->sourceCreatedAt)->equalTo($booking->created_at))) {
            $changes['created_at'] = Carbon::parse($normalized->sourceCreatedAt);
        }

        // updated_at mirrors the source's last modification. When set
        // explicitly it is dirty, so Eloquent's own touch is skipped.
        if ($normalized->sourceUpdatedAt
            && ($booking->updated_at === null || ! Carbon::parse($normalized->sourceUpdatedAt)->equalTo($booking->updated_at))) {
            $changes['updated_at'] = Carbon::parse($normalized->sourceUpdatedAt);
        }

        $newMeta = $this->incomingMetadata($normalized);
        $currentMeta = $booking->metadata ?? [];

        $metaChanged = false;
        foreach ($newMeta as $key => $value) {
            if (! array_key_exists($key, $currentMeta) || $currentMeta[$key] != $value) {
                $metaChanged = true;

                break;
            }
        }

        if ($metaChanged) {
            $changes['metadata'] = array_merge($currentMeta, $newMeta);
        }

        return $changes;
    }

    /** @return array<string,mixed> */
    private function incomingMetadata(NormalizedBooking $normalized): array
    {
        $metadata = $normalized->metadata;

        if ($normalized->email) {
            $metadata['email'] = $normalized->email;
        }

        return array_filter($metadata, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Handle bookings this source used to report but no longer does.
     * Origin: mark vanished (or delete auto-generated availability blocks).
     * Non-origin: just detach the reference, the booking lives on.
     */
    private function handleVanished(Unit $unit, string $sourceKey, array $seenIds, array &$stats): void
    {
        $references = BookingSource::query()
            ->where('source_key', $sourceKey)
            ->whereNotIn('external_id', $seenIds)
            ->whereHas('booking', function ($query) use ($unit) {
                $query->where('unit_id', $unit->id)
                    ->where('check_out', '>=', now()->format('Y-m-d'))
                    ->whereNotIn('status', self::CANCELLED_STATUSES);
            })
            ->with('booking')
            ->get();

        foreach ($references as $reference) {
            $booking = $reference->booking;

            if (! $this->isActingOrigin($booking, $reference)) {
                $reference->delete();

                continue;
            }

            if ($booking->status === 'blocked') {
                $booking->delete();
                $stats['deleted']++;
            } else {
                $booking->update(['status' => 'vanished']);
                $stats['vanished']++;
            }

            Log::info("[SyncEngine] Vanished from {$sourceKey}: booking #{$booking->id} ({$booking->guest_name})");
        }
    }
}
