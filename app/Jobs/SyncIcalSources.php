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

            $totalCreated = 0;
            $totalUpdated = 0;
            $totalDeleted = 0;
            $errors = 0;

            foreach ($sources as $source) {
                try {
                    $stats = $parser->syncSource($source);
                    $totalCreated += $stats["created"] ?? 0;
                    $totalUpdated += $stats["updated"] ?? 0;
                    $totalDeleted += $stats["deleted"] ?? 0;

                    Log::debug("[SyncJob] Synced {$source->name}", $stats);
                } catch (\Exception $e) {
                    $errors++;
                    Log::warning("[SyncJob] Failed to sync {$source->name}", [
                        "error" => $e->getMessage(),
                    ]);
                }
            }

            Log::info("[SyncJob] Synchronization completed", [
                "created" => $totalCreated,
                "updated" => $totalUpdated,
                "deleted" => $totalDeleted,
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
