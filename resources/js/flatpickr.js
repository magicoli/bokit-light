/* SI TU LIS CA C'EST QUE C'EST RETABLI */
/**
 * Flatpickr Date Picker Integration
 *
 * Provides a unified date picker interface with support for:
 * - Single date selection
 * - Date ranges with visual highlight
 * - Minimum stay validation
 * - Disabled dates (calendar sync, blackout periods)
 * - Auto-focus between from/to fields
 */

import flatpickr from "flatpickr";
import { French } from "flatpickr/dist/l10n/fr.js";
import "flatpickr/dist/flatpickr.min.css";

const currentLocale = document.documentElement.lang;

// Configure Flatpickr locale and format
const flatpickrConfig = {
    locale: currentLocale === "fr" ? French : null,
    dateFormat: "Y-m-d", // Server format (ISO)
    altInput: true, // Show user-friendly format
    altFormat: currentLocale === "fr" ? "d/m/Y" : "m/d/Y", // Display format
};

export function initDatePickers(input, options = {}) {
    document.querySelectorAll(".flatpickr-input").forEach((input) => {
        const mode = input.getAttribute("flatpickr-mode") || "single";
        const defaultValue =
            input.getAttribute("value") ||
            input.getAttribute("default") ||
            null;
        var defaultDate = "";
        try {
            defaultDate = JSON.parse(defaultValue);
        } catch {
            defaultDate = defaultValue;
        }

        flatpickr(input, {
            ...flatpickrConfig,
            mode,
            minDate: input.getAttribute("min") || null,
            maxDate: input.getAttribute("max") || null,
            defaultDate: defaultDate,
        });
    });
}

// Auto-initialize on DOM ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDatePickers);
} else {
    initDatePickers();
}
