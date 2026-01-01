<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCapability
{
    /**
     * Handle an incoming request.
     *
     * Check if user has the required capability for the route.
     * Capability can be passed as middleware parameter.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $capability  The capability to check (e.g., 'manage:Property')
     */
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        // If no capability specified, allow access
        if (!$capability) {
            return $next($request);
        }

        // Parse capability format: "ability:Model" or just "role"
        if (str_contains($capability, ':')) {
            [$ability, $model] = explode(':', $capability, 2);
            
            // Check if user can perform ability on model
            if (!user_can($ability, $model)) {
                abort(403, __('app.unauthorized'));
            }
        } else {
            // Simple role check
            if (!user_can($capability)) {
                abort(403, __('app.unauthorized'));
            }
        }

        return $next($request);
    }
}
