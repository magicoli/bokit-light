<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\Options;
use App\Jobs\AutoSyncIcal;

class AutoSync
{
    public function handle(Request $request, Closure $next)
    {
        // Only run auto-sync if installation is complete
        if (!Options::get("install.complete", false)) {
            return $next($request);
        }

        // Check if sync is needed (configurable interval)
        $syncInterval = (int) Options::get("sync.interval", 3600); // Default: 1 hour
        $lastSync = Cache::get("last_auto_sync", 0);
        $now = time();

        if ($now - $lastSync > $syncInterval) {
            // Update timestamp immediately to prevent concurrent syncs
            Cache::put("last_auto_sync", $now, 7200); // 2 hours TTL

            // Dispatch job to run AFTER the HTTP response is sent to the user
            // This is non-blocking for the user, WordPress-style!
            AutoSyncIcal::dispatchAfterResponse();

            Log::debug("[AutoSync] Sync job launched in background");
        }

        return $next($request);
    }
}
