<?php

namespace App\Console\Commands;

use App\Services\SyncRegistry;
use Illuminate\Console\Command;

class SyncIcalFeeds extends Command
{
    protected $signature = 'bokit:sync
                            {--dry-run : Preview sync actions without writing to the database}';

    protected $description = 'Run all registered sync handlers (iCal, Beds24 API, …)';

    public function handle(SyncRegistry $registry): int
    {
        $this->info('🏖️  Starting Bokit calendar synchronization...');
        $this->newLine();

        $handlers = $registry->all();

        if (empty($handlers)) {
            $this->warn('No sync handlers registered.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($handlers as $handler) {
            $this->line("--- <fg=cyan>{$handler->label()}</> ---");
            $handler->handle($this->output, $dryRun);
            $this->newLine();
        }

        $this->info('✅ Synchronization complete!');

        return self::SUCCESS;
    }
}
