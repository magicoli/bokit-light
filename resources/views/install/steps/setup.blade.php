<form id="setupForm" class="space-y-6">
    <!-- Properties Container -->
    <div id="propertiesContainer" class="space-y-6">
        <!-- Properties will be added here -->
    </div>

    <button type="button" onclick="addProperty()" class="w-full py-3 px-4 border-2 border-dashed border-light rounded-lg text-secondary hover:border-primary hover:text-primary transition-colors">
        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Another Property
    </button>
</form>

<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
    <div class="flex items-start">
        <svg class="w-5 h-5 text-primary mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
            <p class="text-sm text-blue-700">
                Create your properties (organizations or companies), their rental units (apartments, villas, cottages),
                and configure calendar synchronization sources for each unit.
            </p>
        </div>
    </div>
</div>

<script>
let propertyCount = 0;

// Add initial property on load
document.addEventListener('DOMContentLoaded', function() {
    addProperty();
});

function addProperty() {
    propertyCount++;
    const container = document.getElementById('propertiesContainer');

    const propertyDiv = document.createElement('div');
    propertyDiv.className = 'border-2 border-light rounded-lg p-4 bg-white property-item relative';
    propertyDiv.dataset.propertyId = propertyCount;
    propertyDiv.innerHTML = `
        <button type="button" onclick="removeProperty(this)" class="absolute top-3 right-3 text-secondary hover:text-red-600 remove-btn hidden">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="space-y-3">
            <!-- Property Name & Slug -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-dark mb-1">
                        Property Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="properties[${propertyCount}][name]"
                        class="property-name w-full px-3 py-2 border border-light rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="My Company"
                        required
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-dark mb-1">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="properties[${propertyCount}][slug]"
                        class="property-slug w-full px-3 py-2 border border-light rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="my-company"
                        required
                    >
                </div>
            </div>

            <!-- Property URL (Optional) -->
            <div>
                <label class="block text-sm font-medium text-dark mb-1">
                    Website <span class="text-xs text-secondary font-normal">(optional)</span>
                </label>
                <input
                    type="url"
                    name="properties[${propertyCount}][url]"
                    class="w-full px-3 py-2 border border-light rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="https://www.example.com"
                >
            </div>

            <!-- Units Container -->
            <div class="mt-3 space-y-3">
                <div class="units-container space-y-3" data-property="${propertyCount}">
                    <!-- Units will be added here -->
                </div>
                <button type="button" onclick="addUnit(${propertyCount})" class="w-full py-2 px-3 border border-dashed border-light rounded-lg text-secondary hover:border-primary hover:text-primary text-sm transition-colors">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Unit
                </button>
            </div>
        </div>
    `;

    container.appendChild(propertyDiv);

    // Setup slug auto-generation
    const nameInput = propertyDiv.querySelector('.property-name');
    const slugInput = propertyDiv.querySelector('.property-slug');

    let manuallyEdited = false;

    nameInput.addEventListener('input', function() {
        if (!manuallyEdited) {
            updateSlug(this.value, slugInput);
        }
    });

    slugInput.addEventListener('input', function() {
        manuallyEdited = true;
    });

    // Add first unit automatically
    addUnit(propertyCount);

    // Update remove buttons visibility
    updateRemoveButtons();
}

function removeProperty(button) {
    button.closest('.property-item').remove();
    updateRemoveButtons();
}

function addUnit(propertyId) {
    const container = document.querySelector(`.units-container[data-property="${propertyId}"]`);
    const unitCount = container.querySelectorAll('.unit-item').length + 1;

    const unitDiv = document.createElement('div');
    unitDiv.className = 'border border-light rounded-lg p-3 bg-gray-50 unit-item relative';
    unitDiv.innerHTML = `
        <button type="button" onclick="removeUnit(this, ${propertyId})" class="absolute top-2 right-2 text-secondary hover:text-red-600 unit-remove-btn hidden">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="space-y-3">
            <!-- Unit Name & Slug -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-dark mb-1">
                        Unit Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="properties[${propertyId}][units][${unitCount}][name]"
                        class="unit-name w-full px-2 py-1.5 text-sm border border-light rounded focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="My Accommodation"
                        required
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-dark mb-1">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="properties[${propertyId}][units][${unitCount}][slug]"
                        class="unit-slug w-full px-2 py-1.5 text-sm border border-light rounded focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="my-accommodation"
                        required
                    >
                </div>
            </div>

            <!-- iCal Sources -->
            <div class="mt-2 space-y-2">
                <div class="ical-sources-container" data-property="${propertyId}" data-unit="${unitCount}">
                    <!-- iCal sources will be added here -->
                </div>
                <button type="button" onclick="addIcalSource(${propertyId}, ${unitCount})" class="w-full py-1.5 px-2 border border-dashed border-light rounded text-secondary hover:border-green-400 hover:text-green-600 text-xs transition-colors">
                    <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Source
                </button>
            </div>
        </div>
    `;

    container.appendChild(unitDiv);

    // Setup slug auto-generation for unit
    const nameInput = unitDiv.querySelector('.unit-name');
    const slugInput = unitDiv.querySelector('.unit-slug');

    let manuallyEdited = false;

    nameInput.addEventListener('input', function() {
        if (!manuallyEdited) {
            updateSlug(this.value, slugInput);
        }
    });

    slugInput.addEventListener('input', function() {
        manuallyEdited = true;
    });

    // Add first iCal source automatically
    addIcalSource(propertyId, unitCount);

    // Update remove buttons visibility
    updateRemoveButtons();
}

function removeUnit(button, propertyId) {
    button.closest('.unit-item').remove();
    updateRemoveButtons();
}

function addIcalSource(propertyId, unitId) {
    const container = document.querySelector(`.ical-sources-container[data-property="${propertyId}"][data-unit="${unitId}"]`);
    const sourceCount = container.querySelectorAll('.ical-source-item').length + 1;

    const sourceDiv = document.createElement('div');
    sourceDiv.className = 'flex items-start gap-2 ical-source-item';
    sourceDiv.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-[120px_1fr] gap-2 flex-1">
            <select
                name="properties[${propertyId}][units][${unitId}][ical_sources][${sourceCount}][type]"
                class="px-2 py-1 text-xs border border-light rounded focus:ring-2 focus:ring-primary focus:border-transparent"
                required
            >
                <option value="ical">iCal</option>
            </select>
            <input
                type="url"
                name="properties[${propertyId}][units][${unitId}][ical_sources][${sourceCount}][url]"
                class="px-2 py-1 text-xs border border-light rounded focus:ring-2 focus:ring-primary focus:border-transparent"
                placeholder="https://calendar.example.com/my-accommodation.ics"
                required
            >
        </div>
        <button type="button" onclick="removeIcalSource(this, ${propertyId}, ${unitId})" class="text-secondary hover:text-red-600 source-remove-btn hidden">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;

    container.appendChild(sourceDiv);

    // Update remove buttons visibility
    updateRemoveButtons();
}

function removeIcalSource(button, propertyId, unitId) {
    button.closest('.ical-source-item').remove();
    updateRemoveButtons();
}

function updateRemoveButtons() {
    // Property remove buttons
    const properties = document.querySelectorAll('.property-item');
    properties.forEach((prop, index) => {
        const removeBtn = prop.querySelector('.remove-btn');
        if (properties.length > 1) {
            removeBtn.classList.remove('hidden');
        } else {
            removeBtn.classList.add('hidden');
        }
    });

    // Unit remove buttons
    document.querySelectorAll('.units-container').forEach(container => {
        const units = container.querySelectorAll('.unit-item');
        units.forEach(unit => {
            const removeBtn = unit.querySelector('.unit-remove-btn');
            if (units.length > 1) {
                removeBtn.classList.remove('hidden');
            } else {
                removeBtn.classList.add('hidden');
            }
        });
    });

    // Source remove buttons
    document.querySelectorAll('.ical-sources-container').forEach(container => {
        const sources = container.querySelectorAll('.ical-source-item');
        sources.forEach(source => {
            const removeBtn = source.querySelector('.source-remove-btn');
            if (sources.length > 1) {
                removeBtn.classList.remove('hidden');
            } else {
                removeBtn.classList.add('hidden');
            }
        });
    });
}

function updateSlug(text, slugInput) {
    const slug = text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

    slugInput.value = slug;
}
</script>
