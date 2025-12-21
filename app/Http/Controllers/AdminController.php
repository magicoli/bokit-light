<?php

namespace App\Http\Controllers;

use App\Support\Options;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Show admin settings page
     */
    public function settings()
    {
        return view('admin.settings');
    }

    /**
     * Save admin settings
     */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'display_timezone' => 'required|timezone',
        ]);

        Options::set('display.timezone', $validated['display_timezone']);

        return redirect()->route('admin.settings')
            ->with('success', __('app.settings_saved'));
    }
}
