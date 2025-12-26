/**
 * Rate Calculator - Date selection handling
 */
document.addEventListener("DOMContentLoaded", function () {
    const checkInField = document.getElementById("calc_check_in");
    const checkOutField = document.getElementById("calc_check_out");

    if (!checkInField || !checkOutField) return;

    // When check-in date changes
    checkInField.addEventListener("change", function () {
        const checkInDate = this.value;
        
        if (!checkInDate) return;

        // Calculate minimum check-out date (check-in + 1 day)
        const minCheckOut = new Date(checkInDate);
        minCheckOut.setDate(minCheckOut.getDate() + 1);
        const minCheckOutStr = minCheckOut.toISOString().split("T")[0];

        // Set min attribute on check-out field
        checkOutField.setAttribute("min", minCheckOutStr);

        // If check-out is before new minimum, update it
        if (checkOutField.value && checkOutField.value < minCheckOutStr) {
            checkOutField.value = minCheckOutStr;
        }

        // Focus on check-out field
        checkOutField.focus();
    });

    // Set initial min on check-out if check-in has a value
    if (checkInField.value) {
        const minCheckOut = new Date(checkInField.value);
        minCheckOut.setDate(minCheckOut.getDate() + 1);
        checkOutField.setAttribute(
            "min",
            minCheckOut.toISOString().split("T")[0],
        );
    }
});
