<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Save general settings
     */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'display_timezone' => 'required|timezone',
        ]);
        
        // Save to options
        options()->set('display.timezone', $validated['display_timezone']);
        
        return redirect()->route('admin.settings')
            ->with('success', __('app.settings_saved'));
    }
}
