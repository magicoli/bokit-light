<?php

namespace App\Console\Commands;

use App\Services\IcalParser;
use Illuminate\Console\Command;

class SyncIcalCommand extends Command
{
    protected $signature = 'bokit:sync';
    protected $description = 'Synchronize all iCal sources';

    public function handle(IcalParser $parser)
    {
        $this->info('Starting iCal synchronization...');

        $results = $parser->syncAll();

        $total = 0;
        $errors = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $total += $result['count'];
            } else {
                $errors++;
            }
        }

        $this->info("Synced {$total} bookings from " . count($results) . " sources");
        
        if ($errors > 0) {
            $this->warn("{$errors} sources failed");
        }

        return 0;
    }
}
