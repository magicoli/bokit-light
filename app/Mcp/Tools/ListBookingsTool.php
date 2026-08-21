<?php

namespace App\Mcp\Tools;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

/**
 * Reads bokit's own already-synced local records (SourceConnector/SyncEngine keep these
 * current) — never calls a channel-manager API directly (bookings-lessons-learned.md §1, §5).
 *
 * Scoped to one property (bokit's tenant, dev/project-app-panel-tenancy.md) — no property-name
 * filter, unlike list_bookings' PAM-era ancestor: there is only ever one property in scope here,
 * the caller already knows which one from the connection itself.
 *
 * Group reservations (several rows sharing group_id, one reservation across several units)
 * collapse to one entry per group, price summed across active members, so the caller never has
 * to remember to check every row itself (§2, §4 — the tool computes this once, not the model).
 */
#[Name('list_bookings')]
#[Title('List Bookings')]
#[Description('List bookings for this property, most recent check-in first. guest_name matches any word in the search against any word in the name, so a partial or slightly-off name still finds it. Cancelled bookings are excluded by default. An empty result with a filter applied does not mean nothing exists — broaden or drop the filter and call again before concluding there is nothing.')]
class ListBookingsTool extends Tool
{
    public function __construct(public ?Property $property = null, public ?User $user = null) {}

    public function handle(Request $request): Response
    {
        if (! $this->property) {
            return Response::error('No tenant context — this tool needs a resolved Property (see BookingServer::boot()).');
        }

        $query = Booking::query()
            ->forUser($this->user)
            ->where('property_id', $this->property->id)
            ->with(['unit', 'property']);

        if (! $request->boolean('include_cancelled')) {
            $query->whereNotIn('status', Booking::CANCELLED_STATUSES);
        }

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status', $status);
        }

        if ($guestName = $request->string('guest_name')->trim()->value()) {
            $words = preg_split('/\s+/', $guestName, flags: PREG_SPLIT_NO_EMPTY) ?: [];
            $query->where(function ($q) use ($words): void {
                foreach ($words as $word) {
                    $q->orWhere('guest_name', 'like', "%{$word}%");
                }
            });
        }

        if ($dateFrom = $request->string('date_from')->trim()->value()) {
            $query->where('check_out', '>=', $dateFrom);
        }

        if ($dateTo = $request->string('date_to')->trim()->value()) {
            $query->where('check_in', '<=', $dateTo);
        }

        $limit = $request->integer('limit', 50);

        // Fetched before collapsing groups, so a limit applied at the SQL level could drop a
        // group's other members before dedup ever sees them — capped generously instead, then
        // limited again after grouping.
        $bookings = $query->orderByDesc('check_in')->limit(500)->get();

        $representatives = $bookings
            ->groupBy(fn (Booking $booking): string => $booking->group_id ? "group:{$booking->group_id}" : "single:{$booking->id}")
            ->map(fn (Collection $members): Booking => $members->sortBy('id')->first())
            ->values()
            ->take($limit);

        return Response::json([
            'bookings' => $representatives->map($this->present(...))->all(),
            'total_matched' => $bookings->pluck('group_id')->filter()->unique()->count()
                + $bookings->whereNull('group_id')->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Booking $booking): array
    {
        $members = $booking->groupMembers();
        $isGroup = $members->count() > 1;
        $active = $isGroup ? $members->reject(fn (Booking $m): bool => $m->isCancelled())->values() : collect([$booking]);

        if ($active->isEmpty()) {
            $active = collect([$booking]);
        }

        $rawPrice = fn (Booking $b): ?float => $b->getRawOriginal('price') !== null
            ? (float) $b->getRawOriginal('price')
            : null;

        $price = $active->contains(fn (Booking $m): bool => $rawPrice($m) !== null)
            ? $active->sum(fn (Booking $m): float => $rawPrice($m) ?? 0)
            : null;

        return [
            'id' => $booking->id,
            'is_group' => $isGroup,
            'group_size' => $isGroup ? $active->count() : null,
            'guest_name' => $booking->guest_name,
            'status' => $booking->status,
            'display_status' => $booking->displayStatus(),
            'check_in' => ($isGroup ? $active->min('check_in') : $booking->check_in)->format('Y-m-d'),
            'check_out' => ($isGroup ? $active->max('check_out') : $booking->check_out)->format('Y-m-d'),
            'unit' => $isGroup ? $active->pluck('unit.name')->filter()->values()->all() : $booking->unit?->name,
            'price' => $price,
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'guest_name' => $schema->string()
                ->description('Filter by guest name — matches any word, tolerant of a partial or misspelled name'),
            'status' => $schema->string()
                ->description('One of: '.implode(', ', Booking::STATUSES)),
            'date_from' => $schema->string()
                ->description('Only bookings whose stay overlaps on or after this date (Y-m-d)'),
            'date_to' => $schema->string()
                ->description('Only bookings whose stay overlaps on or before this date (Y-m-d)'),
            'include_cancelled' => $schema->boolean()
                ->description('Include cancelled/vanished bookings, excluded by default')
                ->default(false),
            'limit' => $schema->integer()
                ->min(1)
                ->max(200)
                ->default(50),
        ];
    }
}
