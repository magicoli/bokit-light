<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CheckInstalled
{
    public function handle(Request $request, Closure $next)
    {
        // Skip check if already on install route
        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        // Check if application is installed
        if (!$this->isInstalled()) {
            return redirect('/install');
        }

        return $next($request);
    }

    private function isInstalled(): bool
    {
        try {
            return Schema::hasTable('units');
        } catch (\Exception $e) {
            return false;
        }
    }
}
