<?php

namespace App\Jobs;

use App\Models\IcalSource;
use App\Services\IcalParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncIcalSources implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("[SyncJob] Starting iCal synchronization");

        try {
            $parser = new IcalParser();
            $sources = IcalSource::with("unit.property")->enabled()->get();

            $totalStats = [
                'total' => 0,
                'new' => 0,
                'updated' => 0,
                'deleted' => 0,
                'vanished' => 0,
            ];
            $errors = 0;

            foreach ($sources as $source) {
                try {
                    $stats = $parser->syncSource($source);
                    
                    if ($stats['success'] ?? false) {
                        foreach (['total', 'new', 'updated', 'deleted', 'vanished'] as $key) {
                            $totalStats[$key] += $stats[$key] ?? 0;
                        }
                        Log::debug("[SyncJob] Synced {$source->name}", $stats);
                    } else {
                        $errors++;
                        Log::warning("[SyncJob] Failed to sync {$source->name}", [
                            "error" => $stats['error'] ?? 'Unknown error',
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::warning("[SyncJob] Failed to sync {$source->name}", [
                        "error" => $e->getMessage(),
                    ]);
                }
            }

            Log::info("[SyncJob] Synchronization completed", [
                "total" => $totalStats['total'],
                "new" => $totalStats['new'],
                "updated" => $totalStats['updated'],
                "deleted" => $totalStats['deleted'],
                "vanished" => $totalStats['vanished'],
                "errors" => $errors,
            ]);
        } catch (\Exception $e) {
            Log::error("[SyncJob] Synchronization failed", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
