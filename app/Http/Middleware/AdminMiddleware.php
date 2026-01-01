<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Debug: Check session status
        Log::debug('AdminMiddleware: Session started?', [
            'session_started' => $request->hasSession(),
            'session_id' => $request->session()->getId() ?? 'no session',
            'auth_check' => auth()->check(),
            'auth_id' => auth()->id(),
            'session_auth' => $request->session()->get('login_web_' . sha1(config('app.name'))),
        ]);
        
        // Check if user is authenticated
        if (!auth()->check()) {
            Log::debug("AdminMiddleware: User is not authenticated, redirecting to login");
            return redirect()->route("login");
        }

        // Check admin gate (defined in AuthServiceProvider)
        if (Gate::denies("admin")) {
            Log::debug("AdminMiddleware: User denied by admin gate, throwing exception");
            throw new AuthorizationException(__("app.unauthorized"));
        }

        Log::debug("AdminMiddleware: User authorized, continuing");
        return $next($request);
    }
}
