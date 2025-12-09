<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class AutoSync
{
    public function handle(Request $request, Closure $next)
    {
        // Check if sync is needed (configurable interval)
        $syncInterval = env('SYNC_INTERVAL', 3600); // Default: 1 hour
        $lastSync = Cache::get('last_auto_sync', 0);
        $now = time();
        
        if ($now - $lastSync > $syncInterval) {
            // Update timestamp immediately to prevent concurrent syncs
            Cache::put('last_auto_sync', $now, 7200); // 2 hours TTL
            
            // Launch sync in background (non-blocking)
            $this->launchSyncInBackground();
        }

        return $next($request);
    }

    private function launchSyncInBackground()
    {
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        
        // Launch command in background without blocking
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            pclose(popen("start /B $php $artisan bokit:sync", "r"));
        } else {
            // Linux/Mac
            exec("$php $artisan bokit:sync > /dev/null 2>&1 &");
        }
    }
}
