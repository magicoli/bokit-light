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

    protected $description = 'Synchronize iCal feeds from external sources';

    public function handle(IcalParser $parser): int
    {
        $this->info('🏖️  Starting Bokit calendar synchronization...');
        $this->newLine();

        // Get sources to sync
        $sources = $this->getSourcesToSync();

        if ($sources->isEmpty()) {
            $this->warn('No active sources found to sync.');
            return self::SUCCESS;
        }

        $this->info("Found {$sources->count()} source(s) to sync");
        $this->newLine();

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalDeleted = 0;
        $errors = 0;

        // Sync each source
        foreach ($sources as $source) {
            $this->line("Syncing: <fg=cyan>{$source->name}</> (Unit: {$source->unit->name})");

            try {
                $stats = $parser->syncSource($source);
                
                $this->line("  ✓ Created: {$stats['created']}, Updated: {$stats['updated']}, Deleted: {$stats['deleted']}");
                $this->line("  Last synced: <fg=green>" . $source->fresh()->last_synced_at->diffForHumans() . "</>");
                
                $totalCreated += $stats['created'];
                $totalUpdated += $stats['updated'];
                $totalDeleted += $stats['deleted'];
                
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                $errors++;
            }

            $this->newLine();
        }

        // Summary
        $this->info('Summary:');
        $this->line("  Bookings created: <fg=green>{$totalCreated}</>");
        $this->line("  Bookings updated: <fg=yellow>{$totalUpdated}</>");
        $this->line("  Bookings deleted: <fg=red>{$totalDeleted}</>");
        
        if ($errors > 0) {
            $this->line("  Errors: <fg=red>{$errors}</>");
        }

        $this->newLine();
        $this->info('✅ Synchronization complete!');

        return self::SUCCESS;
    }

    /**
     * Get the sources to sync based on options
     */
    protected function getSourcesToSync()
    {
        $query = IcalSource::with(['unit.property'])->enabled();

        if ($sourceId = $this->option('source')) {
            $query->where('id', $sourceId);
        }

        if ($propertyId = $this->option('property')) {
            $query->whereHas('unit', function ($q) use ($propertyId) {
                $q->where('property_id', $propertyId);
            });
        }

        return $query->get();
    }
}
