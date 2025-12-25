// Store original data
let allUnits = [];
let allCoupons = [];

// Generic functions
window.updateSelectField = function(selectId, items, itemMapper, defaultText) {
    const selectElement = document.getElementById(selectId);
    if (!selectElement) return;
    
    const currentValue = selectElement.value;
    const placeholder = defaultText || selectElement.querySelector('option[value=""]')?.textContent || 'Select option';
    
    selectElement.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const { value, text } = itemMapper(item);
        const option = document.createElement("option");
        option.value = value;
        option.textContent = text;
        selectElement.appendChild(option);
    });
    selectElement.value = currentValue;
};

window.addNewOption = function(selectId, promptText) {
    const newOption = prompt(promptText);
    if (newOption) {
        const selectElement = document.getElementById(selectId);
        if (selectElement) {
            const option = document.createElement("option");
            option.value = newOption;
            option.textContent = newOption;
            selectElement.appendChild(option);
            selectElement.value = newOption;
        }
    }
};

// Global button functions
window.addNewUnitType = function() {
    addNewOption("unit_type_select", 'Add new unit type:');
};

window.addNewCoupon = function() {
    addNewOption("coupon_select", 'Add new coupon:');
};

document.addEventListener("DOMContentLoaded", function () {
    const propertySelect = document.getElementById("property_select");
    const unitSelect = document.getElementById("unit_select");
    const unitTypeSelect = document.getElementById("unit_type_select");
    const couponSelect = document.getElementById("coupon_select");
    const referenceRateSelect = document.getElementById("reference_rate_select");

    if (!propertySelect || !unitSelect || !unitTypeSelect || !couponSelect || !referenceRateSelect) {
        console.error("Required form elements not found", {
            propertySelect: !!propertySelect,
            unitSelect: !!unitSelect,
            unitTypeSelect: !!unitTypeSelect,
            couponSelect: !!couponSelect,
            referenceRateSelect: !!referenceRateSelect
        });
        return;
    }

    // Load data from data attributes
    if (propertySelect.dataset.units) {
        try {
            allUnits = JSON.parse(propertySelect.dataset.units);
        } catch (e) {
            console.error('Error parsing units data:', e);
        }
    }
    if (propertySelect.dataset.coupons) {
        try {
            allCoupons = JSON.parse(propertySelect.dataset.coupons);
        } catch (e) {
            console.error('Error parsing coupons data:', e);
        }
    }

    // Store default text for reference rates
    const defaultReferenceText = referenceRateSelect.querySelector('option[value=""]')?.textContent || 'Select reference rate';

    // Update when property changes
    propertySelect.addEventListener("change", function () {
        const propertyId = this.value;

        if (!propertyId) {
            // Clear all dependent fields
            updateSelectField("unit_select", []);
            updateSelectField("unit_type_select", []);
            updateSelectField("coupon_select", []);
            updateSelectField("reference_rate_select", [], null, defaultReferenceText);
            return;
        }

        // Update units
        const filteredUnits = allUnits.filter(unit => unit.property_id == propertyId);
        updateSelectField("unit_select", filteredUnits, (unit) => ({
            value: unit.id, 
            text: `${unit.property.name} - ${unit.name}`
        }));

        // Update unit types
        const unitTypes = [...new Set(filteredUnits.filter(unit => unit.unit_type).map(unit => unit.unit_type))];
        updateSelectField("unit_type_select", unitTypes, (type) => ({
            value: type, 
            text: type
        }));

        // Update coupons
        const propertyCoupons = allCoupons.filter(coupon => coupon.property_id == propertyId && coupon.is_active);
        updateSelectField("coupon_select", propertyCoupons, (coupon) => ({
            value: coupon.code, 
            text: `${coupon.code} - ${coupon.name}`
        }));

        // Update reference rates
        fetch(`/api/reference-rates/${propertyId}`)
            .then((response) => response.json())
            .then((rates) => {
                updateSelectField("reference_rate_select", rates, (rate) => ({
                    value: rate.id, 
                    text: `${rate.display_name} - €${rate.base_rate}`
                }), defaultReferenceText);
            })
            .catch((error) =>
                console.error("Error loading reference rates:", error),
            );
    });

    // Clear mutually exclusive fields
    unitSelect.addEventListener("change", function () {
        if (this.value) {
            unitTypeSelect.value = "";
        }
    });

    unitTypeSelect.addEventListener("change", function () {
        if (this.value) {
            unitSelect.value = "";
        }
    });
});
