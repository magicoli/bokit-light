<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Access\AuthorizationException;

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
                throw new AuthorizationException(__('app.unauthorized'));
            }
        } else {
            // Simple role check
            if (!user_can($capability)) {
                throw new AuthorizationException(__('app.unauthorized'));
            }
        }

        return $next($request);
    }
}
