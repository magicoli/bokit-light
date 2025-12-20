<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RenewRememberToken
{
    /**
     * Handle an incoming request.
     * 
     * Renews the "remember me" cookie on each authenticated request
     * to keep users logged in as long as they remain active.
     * Cookie duration: 7 days (10080 minutes)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only renew if user is authenticated and has a remember token
        if (Auth::check() && Auth::viaRemember()) {
            $user = Auth::user();
            $guard = Auth::guard();
            
            // Refresh the remember token
            $user->setRememberToken($token = \Illuminate\Support\Str::random(60));
            $user->save();
            
            // Set new cookie with 7 days duration (10080 minutes)
            $minutes = 10080; // 7 days
            $recaller = $guard->getRecallerName();
            $value = $user->id.'|'.$token.'|'.$user->password;
            
            $response->headers->setCookie(
                cookie(
                    $recaller,
                    encrypt($value),
                    $minutes,
                    config('session.path'),
                    config('session.domain'),
                    config('session.secure'),
                    config('session.http_only', true),
                    false,
                    config('session.same_site')
                )
            );
        }

        return $response;
    }
}
