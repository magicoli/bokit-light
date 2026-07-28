<?php

namespace App\Sync;

use App\Sync\Contracts\PushableConnector;
use App\Models\Property;
use App\Models\Unit;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The one synchronisation procedure.
 *
 * There used to be two: this walk of units and their configured sources, and a separate iCal-only
 * job for the automatic sync. They drifted, as duplicated procedures do — the automatic one kept
 * reading the legacy `ical_sources` table and quietly did nothing once the sources moved into
 * `unit.options.sources`. There is now a single implementation; a caller may narrow WHAT is
 * synced, never HOW.
 *
 * The unit decides the order its platforms are called in, because that order is the unit's
 * priority: `options.sources` is walked as it is stored. Calling the same platform twice for two
 * units is the connectors' problem, and they already cache per property.
 */
class SyncRunner
{
    public function __construct(
        private SyncRegistry $registry,
        private SyncEngine $engine,
    ) {}

    /**
     * @param  iterable<Unit>|null  $units  null syncs every unit of every property
     * @param  Closure(string, array<string, mixed>): void|null  $report  progress events:
     *                                                                   `property`, `unit`, `source`, `push`
     * @return array{
     *     stats: array{total: int, new: int, updated: int, deleted: int, vanished: int},
     *     failures: array<int, string>,
     *     errors: int,
     *     units: int,
     *     sources: int
     * }
     */
    public function run(?iterable $units = null, bool $dryRun = false, ?Closure $report = null): array
    {
        $stats = ['total' => 0, 'new' => 0, 'updated' => 0, 'deleted' => 0, 'vanished' => 0];
        $failures = [];
        $syncedUnits = 0;
        $syncedSources = 0;

        foreach ($this->syncableUnits($units) as $entry) {
            /** @var Unit $unit */
            $unit = $entry['unit'];
            $property = $unit->property;

            if ($report && ($entry['first_of_property'] ?? false)) {
                $report('property', ['property' => $property]);
            }

            $report?->__invoke('unit', ['unit' => $unit, 'property' => $property]);
            $syncedUnits++;

            foreach ($entry['sources'] as $sourceConfig) {
                $connector = $this->registry->getForType($sourceConfig['type']);
                $result = $this->engine->sync($unit, $sourceConfig, $connector, $dryRun);
                $syncedSources++;

                if ($result['success']) {
                    foreach (array_keys($stats) as $key) {
                        $stats[$key] += $result[$key] ?? 0;
                    }
                } else {
                    $failures[] = "{$property->name} / {$unit->name} / {$result['label']}: {$result['error']}";
                }

                $report?->__invoke('source', ['unit' => $unit, 'property' => $property, 'result' => $result]);

                // Bidirectional: push bokit-origin bookings to sources that accept them, when
                // explicitly enabled.
                if (!$connector instanceof PushableConnector || !($sourceConfig['push'] ?? false)) {
                    continue;
                }

                $pushResult = $this->engine->pushBookings($unit, $sourceConfig, $connector, $dryRun);

                if (!$pushResult['success']) {
                    $failures[] = "{$property->name} / {$unit->name} / {$pushResult['label']}: {$pushResult['error']}";
                }

                $report?->__invoke('push', ['unit' => $unit, 'property' => $property, 'result' => $pushResult]);
            }
        }

        return [
            'stats' => $stats,
            'failures' => $failures,
            'errors' => count($failures),
            'units' => $syncedUnits,
            'sources' => $syncedSources,
        ];
    }

    /**
     * Units carrying at least one enabled source this app knows how to handle, in property order,
     * flagged so a caller can print a property heading without grouping the list itself.
     *
     * @param  iterable<Unit>|null  $units
     * @return Collection<int, array{unit: Unit, sources: Collection<int, array<string, mixed>>, first_of_property: bool}>
     */
    private function syncableUnits(?iterable $units): Collection
    {
        $candidates = $units === null
            ? Property::with('units')
                ->orderBy('name')
                ->get()
                ->flatMap(fn(Property $property) => $property->units)
            : collect($units);

        $seenProperties = [];

        // Back to an Eloquent collection: flatMap() and a caller's plain array both hand back a
        // base one, which cannot eager-load.
        return EloquentCollection::make($candidates->all())
            ->loadMissing('property')
            ->map(fn(Unit $unit) => [
                'unit' => $unit,
                'sources' => collect($unit->options['sources'] ?? [])
                    ->filter(
                        fn($source) => (
                            ($source['enabled'] ?? true)
                            && $this->registry->getForType($source['type'] ?? '') !== null
                        ),
                    ),
            ])
            ->filter(fn(array $entry) => $entry['sources']->isNotEmpty())
            ->map(function (array $entry) use (&$seenProperties): array {
                $propertyId = $entry['unit']->property_id;
                $entry['first_of_property'] = !in_array($propertyId, $seenProperties, true);
                $seenProperties[] = $propertyId;

                return $entry;
            })
            ->values();
    }
}
