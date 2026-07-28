<?php

namespace App\Jobs;

use App\Sync\SyncRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The periodic synchronisation, dispatched after the response by {@see \App\Http\Middleware\AutoSync}
 * once the interval has elapsed.
 *
 * It owns no logic of its own: WHAT gets synced is {@see SyncRunner}'s business, the very procedure
 * `bokit:sync` runs. This job only decides that it happens now, and writes the outcome to the log
 * instead of a console. Having had an implementation of its own is exactly what let it go on
 * reading the legacy `ical_sources` table, reporting success while doing nothing at all.
 */
class SyncBookingsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SyncRunner $runner): void
    {
        Log::info('[SyncJob] Starting synchronization');

        try {
            $result = $runner->run();
        } catch (\Throwable $e) {
            Log::error('[SyncJob] Synchronization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        foreach ($result['failures'] as $failure) {
            Log::warning("[SyncJob] {$failure}");
        }

        Log::info('[SyncJob] Synchronization completed', [
            ...$result['stats'],
            'units' => $result['units'],
            'sources' => $result['sources'],
            'errors' => $result['errors'],
        ]);
    }
}
