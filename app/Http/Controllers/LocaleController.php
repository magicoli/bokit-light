<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Supported locales
     */
    private array $supportedLocales = ['en', 'fr'];

    /**
     * Change the application locale
     */
    public function change(Request $request, string $locale)
    {
        if (!in_array($locale, $this->supportedLocales)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);
        
        return redirect()->back();
    }
}
