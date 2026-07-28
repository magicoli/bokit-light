<?php

namespace App\Backup\Http\Middleware;

use App\Backup\Jobs\RunBackupJob;
use App\Support\Options;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Backups ride on the site's traffic, exactly like the synchronisation: a page load notices that
 * the interval has elapsed and the work is dispatched after the response has been sent. No system
 * task to set up — and a site nobody visits is a site where nothing has changed, so there is
 * nothing to back up.
 *
 * Elapsed intervals rather than the Laravel scheduler, deliberately: `schedule:run` only fires the
 * events whose cron expression matches the current minute, so a nightly backup driven by visits
 * would need a visitor arriving in that one minute.
 */
class AutoBackup
{
    /**
     * The order matters: a full backup stands in for the essential one, and the cleanup runs last
     * so it applies the retention to what was just written.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Options::get('install.complete', false) && config('backup.auto.enabled')) {
            $this->dispatchDue();
        }

        return $next($request);
    }

    private function dispatchDue(): void
    {
        $tasks = [];

        if ($this->isDue('full')) {
            $tasks[] = 'full';
            // A full backup contains everything the essential one holds, so it counts as one.
            $this->claim('essential');
        } elseif ($this->isDue('essential')) {
            $tasks[] = 'essential';
        }

        if ($this->isDue('cleanup')) {
            $tasks[] = 'cleanup';
        }

        if ($tasks !== []) {
            RunBackupJob::dispatchAfterResponse($tasks);
        }
    }

    private function isDue(string $task): bool
    {
        $interval = $this->interval($task);

        if ($interval <= 0) {
            return false;
        }

        if ((time() - (int) Cache::get($this->key($task), 0)) < $interval) {
            return false;
        }

        $this->claim($task);

        return true;
    }

    /**
     * Stamped as done before it has run: the next request must not start a second backup while
     * this one is still writing.
     */
    private function claim(string $task): void
    {
        Cache::put($this->key($task), time(), 2 * max($this->interval($task), 3600));
    }

    private function interval(string $task): int
    {
        return (int) config("backup.auto.{$task}");
    }

    private function key(string $task): string
    {
        return "last_auto_backup_{$task}";
    }
}
