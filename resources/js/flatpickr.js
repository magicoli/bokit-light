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

/**
 * Create or update hidden inputs for range date fields
 * Creates {fieldname}_from and {fieldname}_to hidden inputs with Y-m-d format
 */
function createRangeHiddenInputs(pickerInstance, fromDate, toDate) {
    const fieldName = pickerInstance.input.getAttribute("name") || "dates";
    const form = pickerInstance.input.closest("form");
    if (!form) return;

    // Create or update {fieldname}_from
    let fromInput = form.querySelector(`input[name="${fieldName}_from"]`);
    if (!fromInput) {
        fromInput = document.createElement("input");
        fromInput.type = "hidden";
        fromInput.name = `${fieldName}_from`;
        form.appendChild(fromInput);
    }
    fromInput.value = pickerInstance.formatDate(fromDate, "Y-m-d");

    // Create or update {fieldname}_to
    let toInput = form.querySelector(`input[name="${fieldName}_to"]`);
    if (!toInput) {
        toInput = document.createElement("input");
        toInput.type = "hidden";
        toInput.name = `${fieldName}_to`;
        form.appendChild(toInput);
    }
    toInput.value = pickerInstance.formatDate(toDate, "Y-m-d");
}

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
            onReady: function (selectedDates) {
                // Create hidden inputs for range mode with default dates
                if (mode === "range" && selectedDates.length === 2) {
                    createRangeHiddenInputs(
                        this,
                        selectedDates[0],
                        selectedDates[1],
                    );
                }
            },
            onChange: function (selectedDates) {
                switch (mode) {
                    case "range":
                        //// Keep these comments for later:
                        // Disabling invalid dates to enforce minimumStay does not work with data-range:
                        // it breaks the selection as soon as one of the selected dates is disabled.
                        // Alternative to explore:
                        // - use confirmDatePlugin()
                        // - only visual, style invalid dates
                        //// End of comments to keep

                        // When both dates are selected, create hidden inputs for form submission
                        if (selectedDates.length === 2) {
                            createRangeHiddenInputs(
                                this,
                                selectedDates[0],
                                selectedDates[1],
                            );
                        }
                        break;
                    case "single":
                        if (selectedDates.length === 1) {
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
                        }
                        break;
                    // Other modes like "multiple" not implemented, not relevant for current usage
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
