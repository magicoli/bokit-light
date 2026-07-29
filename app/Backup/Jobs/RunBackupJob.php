<?php

namespace App\Backup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * The periodic backup, dispatched after the response by {@see \App\Backup\Http\Middleware\AutoBackup}
 * once the interval has elapsed.
 *
 * It owns no logic of its own: it runs the same commands an operator would, so a backup taken by a
 * page load and one taken before a deploy are the same archive, in the same place, under the same
 * retention.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $tasks  any of 'essential', 'full', 'cleanup', in the order to run them
     */
    public function __construct(
        public array $tasks,
    ) {}

    public function handle(): void
    {
        // This runs in the web process, after the response. A small installation finishes in
        // seconds; a large one archiving gigabytes of uploads would otherwise be killed halfway by
        // max_execution_time, leaving a truncated archive behind.
        set_time_limit(0);

        foreach ($this->tasks as $task) {
            [$command, $arguments] = match ($task) {
                'essential' => ['backup:run', ['--essentials' => true]],
                'full' => ['backup:run', []],
                'cleanup' => ['backup:clean', []],
                default => [null, []],
            };

            if ($command === null) {
                continue;
            }

            try {
                $status = Artisan::call($command, $arguments);
            } catch (\Throwable $e) {
                Log::error("[BackupJob] {$task} backup failed", ['error' => $e->getMessage()]);

                continue;
            }

            if ($status === 0) {
                Log::info("[BackupJob] {$task} backup completed");
            } else {
                // The package's own logging is off, so its account of what went wrong exists
                // nowhere else — this is the only place it can be kept.
                Log::error("[BackupJob] {$task} backup failed", [
                    'status' => $status,
                    'output' => trim(Artisan::output()),
                ]);
            }
        }
    }
}
