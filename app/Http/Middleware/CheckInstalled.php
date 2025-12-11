<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Options;

class CheckInstalled
{
    public function handle(Request $request, Closure $next)
    {
        // Skip check if already on install route
        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        // Check if application is installed - single source of truth
        if (!Options::get('install.complete', false)) {
            return redirect('/install');
        }

        return $next($request);
    }
}
