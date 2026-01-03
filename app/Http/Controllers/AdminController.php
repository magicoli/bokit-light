<?php

namespace App\Http\Controllers;

use App\Traits\SettingsTrait;

class AdminController extends Controller
{
    use SettingsTrait;

    /**
     * Define general settings fields
     */
    public static function settingsFields(): array
    {
        // Get timezone options as array
        $timezones = timezone_identifiers_list();
        $timezoneOptions = array_combine($timezones, $timezones);

        return [
            "capability" => "admin",
            "timezone" => [
                "type" => "select",
                "label" => __("app.display_timezone"),
                "description" => __("app.display_timezone_help"),
                "options" => $timezoneOptions,
                "required" => true,
                "validation" => "required|timezone",
                "attributes" => [
                    "class" => "select2-timezone",
                ],
            ],
        ];
    }
}
