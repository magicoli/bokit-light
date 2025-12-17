<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported locales
     */
    private array $supportedLocales = ['en', 'fr'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if locale is set in session (manual selection)
        if ($request->session()->has('locale')) {
            $locale = $request->session()->get('locale');
            if (in_array($locale, $this->supportedLocales)) {
                app()->setLocale($locale);
                \Carbon\Carbon::setLocale($locale);
                return $next($request);
            }
        }

        // 2. Auto-detect from browser's Accept-Language header
        $browserLocale = $this->detectBrowserLocale($request);
        if ($browserLocale) {
            app()->setLocale($browserLocale);
            \Carbon\Carbon::setLocale($browserLocale);
            return $next($request);
        }

        // 3. Fall back to default locale (en)
        app()->setLocale(config('app.locale'));
        \Carbon\Carbon::setLocale(config('app.locale'));
        return $next($request);
    }

    /**
     * Detect browser's preferred locale
     */
    private function detectBrowserLocale(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');
        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7")
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';', $lang);
            $code = strtolower(trim($parts[0]));
            $quality = isset($parts[1]) ? (float) str_replace('q=', '', $parts[1]) : 1.0;
            
            // Extract primary language code (fr-FR -> fr)
            $primaryCode = explode('-', $code)[0];
            
            $languages[$primaryCode] = $quality;
        }

        // Sort by quality descending
        arsort($languages);

        // Return first supported locale
        foreach (array_keys($languages) as $lang) {
            if (in_array($lang, $this->supportedLocales)) {
                return $lang;
            }
        }

        return null;
    }
}
