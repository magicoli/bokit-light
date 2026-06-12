<?php

namespace App\Console\Commands;

use App\Contracts\PushableConnector;
use App\Models\Property;
use App\Services\SyncEngine;
use App\Services\SyncRegistry;
use Illuminate\Console\Command;

class SyncIcalFeeds extends Command
{
    protected $signature = 'bokit:sync
                            {--dry-run : Preview sync actions without writing to the database}';

    protected $description = 'Sync all unit sources (Beds24, iCal, HBook, Multipass, …), grouped by property and unit';

    public function handle(SyncRegistry $registry, SyncEngine $engine): int
    {
        if (empty($registry->all())) {
            $this->warn('No sync handlers registered.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line('<fg=yellow>DRY RUN — no changes will be saved.</>');
            $this->newLine();
        }

        $this->info('🏖️  Starting Bokit calendar synchronization...');
        $this->newLine();

        $properties = Property::with('units')->orderBy('name')->get();
        $failures = [];

        foreach ($properties as $property) {
            // Skip properties that have no units with at least one enabled, handleable source.
            $syncableUnits = $property->units->map(fn ($unit) => [
                'unit' => $unit,
                'sources' => collect($unit->options['sources'] ?? [])
                    ->filter(fn ($s) => ($s['enabled'] ?? true) && $registry->getForType($s['type'] ?? '') !== null),
            ])->filter(fn ($entry) => $entry['sources']->isNotEmpty());

            if ($syncableUnits->isEmpty()) {
                continue;
            }

            $this->line("Property: <info>{$property->name}</info>");

            foreach ($syncableUnits as ['unit' => $unit, 'sources' => $sources]) {
                $this->line("  Unit: <fg=cyan>{$unit->name}</>");

                foreach ($sources as $sourceConfig) {
                    $connector = $registry->getForType($sourceConfig['type']);
                    $result = $engine->sync($unit, $sourceConfig, $connector, $dryRun);

                    if ($result['success']) {
                        $this->line('    ✓ '.$this->formatStats($result));
                    } else {
                        $this->line("    <error>✗ {$result['label']}: {$result['error']}</error>");
                        $failures[] = "{$property->name} / {$unit->name} / {$result['label']}: {$result['error']}";
                    }

                    // Bidirectional: push bokit-origin bookings to sources
                    // that accept them, when explicitly enabled.
                    if ($connector instanceof PushableConnector && ($sourceConfig['push'] ?? false)) {
                        $pushResult = $engine->pushBookings($unit, $sourceConfig, $connector, $dryRun);

                        if ($pushResult['created'] || $pushResult['updated'] || $pushResult['failed']) {
                            $line = "    ↑ {$pushResult['label']}: Created: {$pushResult['created']}, Updated: {$pushResult['updated']}, Failed: {$pushResult['failed']}";
                            $this->line($pushResult['success'] ? $line : "<error>{$line} — {$pushResult['error']}</error>");
                        }

                        if (! $pushResult['success']) {
                            $failures[] = "{$property->name} / {$unit->name} / {$pushResult['label']}: {$pushResult['error']}";
                        }
                    }
                }
            }

            $this->newLine();
        }

        if (! empty($failures)) {
            $this->line('Failures:');
            foreach ($failures as $failure) {
                $this->line("  <error>✗ {$failure}</error>");
            }
            $this->newLine();
        }

        $this->info('✅ Synchronization complete!');

        return self::SUCCESS;
    }

    private function formatStats(array $r): string
    {
        $parts = [
            "Total: {$r['total']}",
            ($r['new'] > 0 ? '<fg=green>' : '')."New: {$r['new']}".($r['new'] > 0 ? '</>' : ''),
            ($r['updated'] > 0 ? '<fg=yellow>' : '')."Updated: {$r['updated']}".($r['updated'] > 0 ? '</>' : ''),
            ($r['deleted'] > 0 ? '<fg=red>' : '')."Deleted: {$r['deleted']}".($r['deleted'] > 0 ? '</>' : ''),
            ($r['vanished'] > 0 ? '<fg=magenta>' : '')."Vanished: {$r['vanished']}".($r['vanished'] > 0 ? '</>' : ''),
        ];

        return "{$r['label']}: ".implode(', ', $parts);
    }
}
