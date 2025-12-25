<?php
/**
 * DO NOT HANDLE FORM GENERATION YET
 * WE ONLY CARE FOR THE FEW STATIC FUNCTIONS WE NEED RIGHT NOW
 **/

namespace App\Forms;

class Form
{
    /**
     * Generate HTML options with proper selected attribute
     *
     * @param array $options Array of value => text pairs
     * @param mixed $selectedValue Currently selected value
     * @return string HTML options
     */
    public static function selectOptions(
        array $options,
        $selectedValue = null,
    ): string {
        $html = "";
        foreach ($options as $value => $text) {
            $html .= sprintf(
                "<option value=\"%s\" %s>%s</option>",
                $value,
                $selectedValue == $value ? "selected" : "",
                $text,
            );
        }
        return $html;
    }

    /**
     * Get or set an option value from request/input
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function option($key, $default = null): mixed
    {
        if (request()->has($key)) {
            return request()->input($key);
        }

        return old($key, $default);
    }
}
