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

export function initDatePickers(picker, options = {}) {
    document.querySelectorAll(".flatpickr-input").forEach((picker) => {
        const mode = picker.getAttribute("flatpickr-mode") || "single";

        // Parse default value (handles both strings and JSON arrays)
        const defaultValue =
            picker.getAttribute("value") ||
            picker.getAttribute("default") ||
            null;
        let defaultDate = null;
        if (defaultValue) {
            try {
                defaultDate = JSON.parse(defaultValue);
            } catch {
                defaultDate = defaultValue;
            }
        }

        // Get minimum nights for range validation
        const minimumStay = parseInt(picker.dataset.minimumStay) || 0;
        flatpickr(picker, {
            ...flatpickrConfig,
            mode,
            minDate: picker.getAttribute("min") || null,
            maxDate: picker.getAttribute("max") || null,
            defaultDate: defaultDate,
            allowInvalidPreload: true,
            onChange: function (selectedDates) {
                if (selectedDates.length === 1) {
                    // if (mode === "range") {
                    switch (mode) {
                        case "range":
                            // Testing only: disabling past dates does not actually enforce minimum stay
                            this.set(
                                "minDate",
                                new Date(selectedDates[0]),
                                // new Date(selectedDates[0]).fp_incr(minimumStay),
                            );
                            // Alternative: use confirmDatePlugin()
                            // Alternative: only visual, style invalid dates
                            break;
                        case "single":
                            const toId = this.input.id.replace("_from", "_to");
                            const toInput = document.getElementById(toId);
                            if (toInput && toInput._flatpickr) {
                                toInput._flatpickr.set(
                                    "minDate",
                                    new Date(selectedDates[0]).fp_incr(
                                        minimumStay,
                                    ),
                                );
                            }
                            break;
                        // Not relevant for "multiple" third mode
                    }
                }
            },
        });
    });
}

// Auto-initialize on DOM ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDatePickers);
} else {
    initDatePickers();
}
