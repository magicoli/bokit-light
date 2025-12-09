<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class WordPressAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user is already authenticated
        if (Session::has("wp_user")) {
            return $next($request);
        }

        // Check if this is a login attempt
        if (
            $request->isMethod("post") &&
            $request->has(["username", "password"])
        ) {
            return $this->authenticate($request, $next);
        }

        // Show login form
        return $this->showLoginForm();
    }

    private function authenticate(Request $request, Closure $next)
    {
        $wpUrl = env("WP_SITE_URL", "https://gites-mosaiques.com");
        $requiredRole = env("WP_REQUIRED_ROLE", "bokit_manager");

        try {
            // Verify credentials via custom WP endpoint
            $response = Http::post($wpUrl . "/wp-json/bokit/v1/auth", [
                "username" => $request->username,
                "password" => $request->password,
            ]);

            if ($response->successful()) {
                $user = $response->json();

                // Check if user has required role
                if (
                    in_array($requiredRole, $user["roles"] ?? []) ||
                    in_array("administrator", $user["roles"] ?? [])
                ) {
                    Session::put("wp_user", [
                        "id" => $user["id"],
                        "name" => $user["name"],
                        "roles" => $user["roles"],
                    ]);

                    return redirect("/");
                }

                return back()->with(
                    "error",
                    "Access denied. You do not have permission to access this application.",
                );
            }

            $error = $response->json();
            return back()->with(
                "error",
                $error["message"] ?? "Invalid credentials",
            );
        } catch (\Exception $e) {
            return back()->with(
                "error",
                "Authentication failed: " . $e->getMessage(),
            );
        }
    }

    private function showLoginForm()
    {
        return response()->view("auth.login");
    }
}
