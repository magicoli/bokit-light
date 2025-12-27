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
 * Initialize a single date range input
 *
 * @param {HTMLInputElement} input - The input to transform into range picker
 * @param {Object} options - Configuration options
 * @returns {Object} Flatpickr instance
 */
export function initDateRangePicker(input, options = {}) {
    const {
        minNights = parseInt(input.dataset.minNights) || 0,
        disable = [],
        onChange = null
    } = options;

    // Get min date from HTML standard attribute
    const minAttr = input.getAttribute('min');
    const minDate = minAttr || null;

    const rangePicker = flatpickr(input, {
        ...flatpickrConfig,
        mode: "range",
        minDate,
        disable,
        onChange: (selectedDates) => {
            if (selectedDates.length === 2) {
                const [start, end] = selectedDates;
                
                // Check minimum nights
                const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                if (minNights > 0 && daysDiff < minNights) {
                    // Reset if less than minimum
                    rangePicker.clear();
                    return;
                }
                
                if (onChange) {
                    onChange(start, end);
                }
            }
        },
    });

    // Set initial value from HTML value attribute
    const initialValue = input.getAttribute('value');
    if (initialValue && initialValue.includes(' to ')) {
        const [start, end] = initialValue.split(' to ');
        rangePicker.setDate([start.trim(), end.trim()]);
    }

    return rangePicker;
}

/**
 * Initialize all date pickers on the page
 * Looks for inputs with class 'date-range-input'
 */
export function initAllDatePickers() {
    document.querySelectorAll('.date-range-input').forEach((input) => {
        initDateRangePicker(input);
    });
}

// Auto-initialize on DOM ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAllDatePickers);
} else {
    initAllDatePickers();
}

// Export for manual initialization
export default {
    initDateRangePicker,
    initAllDatePickers,
};
