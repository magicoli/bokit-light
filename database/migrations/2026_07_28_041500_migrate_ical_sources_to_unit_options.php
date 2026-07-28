<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the legacy `ical_sources` rows into `units.options.sources`, where every source type has
 * been declared since the sync engine landed, and drop the Beds24 feeds the API already covers.
 *
 * This used to be an artisan command, which was a mistake: the running code DEPENDS on it having
 * happened — a unit whose feeds live only in the old table is simply not synced any more. A
 * command is something someone remembers to run; a migration is something a deploy runs. That is
 * exactly the difference between the local database, migrated by hand in June, and production,
 * where one unit still had its two feeds in the old table alone.
 *
 * Plain queries rather than Eloquent on purpose: a migration must keep working when the models it
 * once used are renamed or deleted, and `IcalSource` is meant to go.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrateLegacySources();
        $this->dropBeds24FeedsCoveredByTheApi();
    }

    /**
     * Irreversible by nature: the rows are gone and the units now declare their sources the only
     * way the app reads them. Rolling back would mean re-creating data the app no longer uses.
     */
    public function down(): void {}

    private function migrateLegacySources(): void
    {
        if (! Schema::hasTable('ical_sources')) {
            return;
        }

        foreach (DB::table('ical_sources')->get() as $source) {
            $unit = DB::table('units')->where('id', $source->unit_id)->first();

            // Belt and braces: the foreign key makes an orphan impossible, but a data migration
            // that trips over one row leaves the rest unmigrated, and nobody notices until a unit
            // stops syncing.
            if (! $unit) {
                DB::table('ical_sources')->where('id', $source->id)->delete();

                continue;
            }

            $options = json_decode($unit->options ?? '', true) ?: [];
            $sources = $options['sources'] ?? [];

            $alreadyPresent = collect($sources)
                ->contains(fn ($declared) => ($declared['url'] ?? '') === $source->url);

            if (! $alreadyPresent) {
                $sources[] = array_filter([
                    'type' => 'ical',
                    'url' => $source->url,
                    // `name` only exists on databases created before the initial-schema
                    // migrations started guarding each other; a fresh install has no such column.
                    'label' => $source->name ?? null,
                    'enabled' => (bool) $source->sync_enabled,
                ], fn ($value) => $value !== null);

                $this->storeSources($unit->id, $options, $sources);
            }

            DB::table('ical_sources')->where('id', $source->id)->delete();
        }
    }

    /**
     * A unit can end up with a Beds24 API source and an api.beds24.com iCal feed for the same
     * room, fetching the same bookings twice for one result.
     *
     * Decided, never guessed: the feed URL carries `roomid`, so it is either the room the API
     * source already reads — and then it is redundant by definition — or another one, and then it
     * is an intentional cross-block (blocking one unit while another is taken) that must be left
     * alone. A unit with no API source keeps its feed too: that feed is its only link to Beds24.
     */
    private function dropBeds24FeedsCoveredByTheApi(): void
    {
        foreach (DB::table('units')->whereNotNull('options')->get() as $unit) {
            $options = json_decode($unit->options ?? '', true) ?: [];
            $sources = $options['sources'] ?? [];

            $apiRoomId = collect($sources)
                ->filter(fn ($source) => ($source['type'] ?? '') === 'beds24')
                ->map(fn ($source) => (string) ($source['room_id'] ?? ''))
                ->filter()
                ->first();

            if (! $apiRoomId) {
                continue;
            }

            $kept = collect($sources)
                ->reject(fn ($source) => ($source['type'] ?? '') === 'ical'
                    && str_contains($source['url'] ?? '', 'api.beds24.com')
                    && $this->roomIdOf($source['url'] ?? '') === $apiRoomId)
                ->values()
                ->all();

            if (count($kept) !== count($sources)) {
                $this->storeSources($unit->id, $options, $kept);
            }
        }
    }

    private function roomIdOf(string $url): ?string
    {
        preg_match('/[?&]roomid=(\d+)/i', $url, $matches);

        return $matches[1] ?? null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<int, array<string, mixed>>  $sources
     */
    private function storeSources(int $unitId, array $options, array $sources): void
    {
        $options['sources'] = $sources;

        DB::table('units')->where('id', $unitId)->update(['options' => json_encode($options)]);
    }
};
