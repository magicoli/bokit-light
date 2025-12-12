<?php

namespace App\Console\Commands;

use App\Services\IcalParser;
use App\Models\IcalSource;
use Illuminate\Console\Command;

class SyncIcalFeeds extends Command
{
    protected $signature = 'bokit:sync
                            {--source= : Sync only a specific source ID}
                            {--property= : Sync only sources for a specific property ID}';

    protected $description = "Synchronize iCal feeds from external sources";

    public function handle(IcalParser $parser): int
    {
        $this->info("🏖️  Starting Bokit calendar synchronization...");
        $this->newLine();

        // Get sources to sync
        $sources = $this->getSourcesToSync();

        if ($sources->isEmpty()) {
            $this->warn("No active sources found to sync.");
            return self::SUCCESS;
        }

        $this->info("Found {$sources->count()} source(s) to sync");
        $this->newLine();

        $totalStats = [
            'total' => 0,
            'new' => 0,
            'updated' => 0,
            'deleted' => 0,
            'vanished' => 0,
        ];
        $errors = 0;

        // Sync each source
        foreach ($sources as $source) {
            $this->line(
                "Syncing: {$source->unit->property->name} {$source->unit->name} <fg=cyan>{$source->name}</>",
            );

            try {
                $stats = $parser->syncSource($source);
                
                if (!($stats["success"] ?? false)) {
                    $errors++;
                    throw new \Exception($stats["error"] ?? "Unknown error");
                }
                
                // Display per-source stats
                $parts = [];
                if ($stats['new'] > 0) $parts[] = "<fg=green>{$stats['new']} new</>";
                if ($stats['updated'] > 0) $parts[] = "<fg=yellow>{$stats['updated']} updated</>";
                if ($stats['deleted'] > 0) $parts[] = "<fg=red>{$stats['deleted']} deleted</>";
                if ($stats['vanished'] > 0) $parts[] = "<fg=magenta>{$stats['vanished']} vanished</>";
                
                $this->line("  ✓ " . ($parts ? implode(", ", $parts) : "No changes"));
                
                // Accumulate totals
                foreach (['total', 'new', 'updated', 'deleted', 'vanished'] as $key) {
                    $totalStats[$key] += $stats[$key] ?? 0;
                }
                
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                $errors++;
            }

            $this->newLine();
        }

        // Summary
        $this->info("Summary:");
        $this->line("  Total bookings: <fg=cyan>{$totalStats['total']}</>");
        $this->line("  New: <fg=green>{$totalStats['new']}</>");
        $this->line("  Updated: <fg=yellow>{$totalStats['updated']}</>");
        $this->line("  Deleted: <fg=red>{$totalStats['deleted']}</>");
        $this->line("  Vanished: <fg=magenta>{$totalStats['vanished']}</>");

        if ($errors > 0) {
            $this->line("  Errors: <fg=red>{$errors}</>");
        }

        $this->newLine();
        $this->info("✅ Synchronization complete!");

        return self::SUCCESS;
    }

    /**
     * Get the sources to sync based on options
     */
    protected function getSourcesToSync()
    {
        $query = IcalSource::with(["unit.property"])->enabled();

        if ($sourceId = $this->option("source")) {
            $query->where("id", $sourceId);
        }

        if ($propertyId = $this->option("property")) {
            $query->whereHas("unit", function ($q) use ($propertyId) {
                $q->where("property_id", $propertyId);
            });
        }

        return $query->get();
    }
}
