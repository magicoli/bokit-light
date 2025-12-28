// Store global data
let allUnits = [];
let allCoupons = [];
let allUnitTypes = [];

/**
 * Generic function to update a select field with new options
 */
window.updateSelectOptions = function (
    selectId,
    items,
    valueKey,
    textKey,
    placeholder,
) {
    const select = document.getElementById(selectId);
    if (!select) return;

    const currentValue = select.value;
    
    // Save existing placeholder BEFORE clearing
    if (!placeholder) {
        const emptyOption = select.querySelector('option[value=""]');
        placeholder = emptyOption ? emptyOption.textContent : 'Select...';
    }

    select.innerHTML = `<option value="">${placeholder}</option>`;

    items.forEach((item) => {
        const option = document.createElement("option");
        option.value =
            typeof valueKey === "function" ? valueKey(item) : item[valueKey];
        option.textContent =
            typeof textKey === "function" ? textKey(item) : item[textKey];
        select.appendChild(option);
    });

    // Restore previous value if still valid
    if (
        currentValue &&
        Array.from(select.options).some((opt) => opt.value === currentValue)
    ) {
        select.value = currentValue;
    }
};

/**
 * Add custom option to a select
 */
function addCustomOption(selectId, type) {
    const select = document.getElementById(selectId);
    if (!select) return;

    const label = type === "unit_type" ? "Unit Type" : "Coupon Code";
    const value = prompt(`Enter new ${label}:`);

    if (value && value.trim()) {
        // Add option to select
        const option = document.createElement("option");
        option.value = value.trim();
        option.textContent = value.trim();
        option.selected = true;
        select.appendChild(option);

        // Trigger change event to update name
        select.dispatchEvent(new Event("change"));
    }
}

/**
 * Update the displayed rate name based on selected scope
 */
function updateRateName() {
    const nameField = document.getElementById("name");
    if (!nameField) return;

    const property = document.getElementById("property_id");
    const unitType = document.getElementById("unit_type");
    const unit = document.getElementById("unit_id");
    const coupon = document.getElementById("coupon_code");
    const suffix = document.getElementById("suffix");

    const parts = [];

    // Property name
    if (property && property.value) {
        const selectedOption = property.options[property.selectedIndex];
        if (selectedOption && selectedOption.text) {
            parts.push(selectedOption.text);
        }
    }

    // Unit type
    if (unitType && unitType.value) {
        parts.push(unitType.value);
    }

    // Unit name
    if (unit && unit.value) {
        const selectedOption = unit.options[unit.selectedIndex];
        if (selectedOption && selectedOption.text) {
            parts.push(selectedOption.text);
        }
    }

    // Coupon
    if (coupon && coupon.value) {
        parts.push("Coupon: " + coupon.value);
    }

    // Suffix
    if (suffix && suffix.value) {
        parts.push(suffix.value);
    }

    nameField.value = parts.join(" - ") || "";
}

/**
 * Handle parent rate selection
 */
function setupParentRateSync() {
    const baseField = document.getElementById("base");
    const parentRateSelect = document.getElementById("parent_rate_id");

    if (!baseField || !parentRateSelect) return;

    parentRateSelect.addEventListener("change", function () {
        if (this.value) {
            // Find the selected parent rate
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.base) {
                // Copy parent's base to this rate's base
                baseField.value = selectedOption.dataset.base;
                baseField.readOnly = true;
                baseField.classList.add("readonly");
            }
        } else {
            // No parent selected, make base editable
            baseField.readOnly = false;
            baseField.classList.remove("readonly");
        }
    });

    // Initial state
    if (parentRateSelect.value) {
        const selectedOption =
            parentRateSelect.options[parentRateSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.base) {
            baseField.value = selectedOption.dataset.base;
            baseField.readOnly = true;
            baseField.classList.add("readonly");
        }
    }
}

/**
 * Handle mutually exclusive fields (unit_id and unit_type)
 */
function setupMutuallyExclusive(field1Id, field2Id) {
    const field1 = document.getElementById(field1Id);
    const field2 = document.getElementById(field2Id);

    if (!field1 || !field2) return;

    field1.addEventListener("change", function () {
        if (this.value) {
            field2.value = "";
        }
    });

    field2.addEventListener("change", function () {
        if (this.value) {
            field1.value = "";
        }
    });
}

/**
 * Add "+Add" buttons for custom options
 */
function addCustomOptionButtons() {
    document.querySelectorAll("select[data-add-new]").forEach((select) => {
        const type = select.getAttribute("data-add-new");
        const fieldset = select.closest("fieldset");

        if (fieldset && !fieldset.querySelector(".add-custom-btn")) {
            // Add input-group class to fieldset
            fieldset.classList.add("input-group");

            // Wrap select + button in items div
            const itemsDiv = document.createElement("div");
            itemsDiv.className = "items";

            // Move select into items div
            const label = fieldset.querySelector("label");
            select.parentNode.insertBefore(itemsDiv, select);
            itemsDiv.appendChild(select);

            // Create and add button
            const button = document.createElement("button");
            button.type = "button";
            button.className = "add-custom-btn";
            button.textContent = "+";
            button.title = `Add custom ${type}`;
            button.onclick = () => addCustomOption(select.id, type);

            itemsDiv.appendChild(button);
        }
    });
}

/**
 * Initialize rates form
 */
document.addEventListener("DOMContentLoaded", function () {
    const propertySelect = document.getElementById("property_id");
    if (!propertySelect) return;

    const unitTypeSelect = document.getElementById("unit_type");
    const unitSelect = document.getElementById("unit_id");
    const couponSelect = document.getElementById("coupon_code");
    const parentRateSelect = document.getElementById("parent_rate_id");
    const suffixField = document.getElementById("suffix");

    // Load data from window (passed by Blade)
    if (window.ratesFormData) {
        allUnits = window.ratesFormData.units || [];
        allCoupons = window.ratesFormData.coupons || [];
        allUnitTypes = window.ratesFormData.unitTypes || [];
    }

    // Setup parent rate synchronization
    setupParentRateSync();

    // Setup mutually exclusive for unit/unit_type only
    setupMutuallyExclusive("unit_id", "unit_type");

    // Add custom option buttons
    addCustomOptionButtons();

    // Update dependent fields when property changes
    propertySelect.addEventListener("change", function () {
        const propertyId = this.value;

        if (!propertyId) {
            // Clear all dependent fields
            if (unitTypeSelect)
                updateSelectOptions("unit_type", [], "value", "text");
            if (unitSelect) updateSelectOptions("unit_id", [], "id", "name");
            if (couponSelect)
                updateSelectOptions("coupon_code", [], "code", "code");
            if (parentRateSelect)
                updateSelectOptions("parent_rate_id", [], "id", "display_name");
            updateRateName();
            return;
        }

        // Update unit types - use all available types (from units AND rates)
        if (unitTypeSelect) {
            const unitTypeOptions = allUnitTypes.map((type) => ({
                value: type,
                text: type,
            }));
            updateSelectOptions("unit_type", unitTypeOptions, "value", "text");
        }

        // Update units
        if (unitSelect) {
            const propertyUnits = allUnits.filter(
                (u) => u.property_id == propertyId,
            );
            updateSelectOptions(
                "unit_id",
                propertyUnits,
                "id",
                (unit) => unit.name,
            );
        }

        // Update coupons
        if (couponSelect) {
            const propertyCoupons = allCoupons.filter(
                (c) => c.property_id == propertyId && c.is_active,
            );
            updateSelectOptions(
                "coupon_code",
                propertyCoupons,
                "code",
                (coupon) =>
                    coupon.code + (coupon.name ? " - " + coupon.name : ""),
            );
        }

        // Update parent rates via API
        if (parentRateSelect) {
            fetch(`/api/parent-rates/${propertyId}`)
                .then((response) => response.json())
                .then((rates) => {
                    // Clear existing options
                    parentRateSelect.innerHTML = `<option value="">${parentRateSelect.querySelector('option[value=""]')?.textContent || "No parent rate"}</option>`;

                    // Add new options with data-base attribute
                    rates.forEach((rate) => {
                        const option = document.createElement("option");
                        option.value = rate.id;
                        option.textContent = `${rate.display_name} - ${Number(rate.base).toFixed(2)}`;
                        option.dataset.base = rate.base;
                        parentRateSelect.appendChild(option);
                    });
                })
                .catch((error) =>
                    console.error("Error loading parent rates:", error),
                );
        }

        updateRateName();
    });

    // Update rate name when any scope field changes
    [unitTypeSelect, unitSelect, couponSelect, suffixField].forEach(
        (element) => {
            if (element) {
                element.addEventListener("change", updateRateName);
                element.addEventListener("input", updateRateName);
            }
        },
    );

    // Initial update if property is already selected (for edit forms)
    if (propertySelect.value) {
        propertySelect.dispatchEvent(new Event("change"));
    }
});
