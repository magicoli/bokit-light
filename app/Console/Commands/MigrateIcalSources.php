<?php

namespace App\Console\Commands;

use App\Models\IcalSource;
use App\Models\Unit;
use Illuminate\Console\Command;

/**
 * One-time migration: copies legacy IcalSource DB records into the
 * unit.options.sources JSON array (new format) and deletes the originals.
 *
 * After this command completes, iCal sources are managed exclusively
 * via the unit edit page in the admin panel.
 */
class MigrateIcalSources extends Command
{
    protected $signature = 'bokit:migrate-ical-sources
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Migrate legacy IcalSource records into unit.options.sources';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN — no changes will be saved.');
            $this->newLine();
        }

        $sources = IcalSource::with('unit')->get();

        if ($sources->isEmpty()) {
            $this->info('No IcalSource records found — nothing to migrate.');

            return self::SUCCESS;
        }

        $this->info("Found {$sources->count()} IcalSource record(s) to migrate.");
        $this->newLine();

        $migrated = 0;
        $skipped = 0;

        foreach ($sources as $source) {
            $unit = $source->unit;

            if (! $unit) {
                $this->warn("  ✗ Source #{$source->id} \"{$source->name}\" has no unit — skipping.");
                $skipped++;

                continue;
            }

            $existing = collect($unit->options['sources'] ?? []);
            $alreadyPresent = $existing->contains(fn ($s) => ($s['url'] ?? '') === $source->url);

            if ($alreadyPresent) {
                $this->line("  ~ <fg=gray>Already in options.sources:</> <fg=cyan>{$unit->name}</> / {$source->name} ({$source->url})");
                $skipped++;
            } else {
                $this->line("  ✓ <fg=green>Migrating:</> <fg=cyan>{$unit->name}</> / {$source->name} → options.sources");

                if (! $dryRun) {
                    $options = $unit->options ?? [];
                    $options['sources'][] = [
                        'type' => 'ical',
                        'url' => $source->url,
                        'label' => $source->name,
                        'enabled' => $source->sync_enabled,
                    ];
                    $unit->update(['options' => $options]);
                }

                $migrated++;
            }

            if (! $dryRun) {
                $source->delete();
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Would migrate: {$migrated}, skip (already present): {$skipped}");
        } else {
            $this->info("Migrated: {$migrated}, skipped (already present): {$skipped}");
        }

        return self::SUCCESS;
    }
}
