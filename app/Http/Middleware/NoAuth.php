<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoAuth
{
    /**
     * Pass through without authentication
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
