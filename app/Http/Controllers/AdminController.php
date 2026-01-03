<?php

namespace App\Http\Controllers;

use App\Support\Form;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Show general settings page
     */
    public function settings()
    {
        // Get current values
        $values = [
            'display_timezone' => options('display.timezone', config('app.timezone', 'UTC')),
        ];

        // Create form
        $form = new Form(
            $values,
            fn() => self::settingsFields(),
            route('admin.settings.save')
        );

        return view('admin.settings', [
            'form' => $form,
        ]);
    }

    /**
     * Define general settings fields
     */
    private static function settingsFields(): array
    {
        // Get timezone options as array
        $timezones = timezone_identifiers_list();
        $timezoneOptions = array_combine($timezones, $timezones);

        return [
            'display_timezone' => [
                'type' => 'select',
                'label' => __('app.display_timezone'),
                'description' => __('app.display_timezone_help'),
                'options' => $timezoneOptions,
                'required' => true,
                'attributes' => [
                    'class' => 'select2-timezone',
                ],
            ],
        ];
    }

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
