<div class="text-center mb-8">
    <div class="text-5xl mb-3">{{ config('app.logo') }}</div>
    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ config('app.name') }}</h1>
    <p class="text-gray-600">{{ config('app.slogan') }}</p>
</div>

<div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-2">Create Your Rental Units</h2>
    <p class="text-gray-600 text-sm">
        Add the rental units (apartments, houses, rooms) that you want to manage in the calendar.
    </p>
</div>

<form id="units-form" onsubmit="event.preventDefault(); submitUnitsForm();" class="space-y-6">
    <div id="units-container">
        <!-- Units will be added here -->
    </div>

    <button
        type="button"
        onclick="addUnit()"
        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-lg transition-colors border-2 border-dashed border-gray-300">
        + Add Another Unit
    </button>

    <button
        type="submit"
        data-loading="Creating Units..."
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
        Create Units & Complete Installation
    </button>
</form>

<script>
    let unitCount = 0;

    function addUnit() {
        unitCount++;
        const container = document.getElementById('units-container');
        
        const unitDiv = document.createElement('div');
        unitDiv.className = 'border border-gray-300 rounded-lg p-4 space-y-4';
        unitDiv.dataset.unitIndex = unitCount;
        unitDiv.innerHTML = `
            <div class="flex justify-between items-center mb-2">
                <h3 class="font-semibold text-gray-900">Unit #${unitCount}</h3>
                ${unitCount > 1 ? `<button type="button" onclick="removeUnit(${unitCount})" class="text-red-600 hover:text-red-800 text-sm">Remove</button>` : ''}
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Unit Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    name="units[${unitCount}][name]" 
                    id="unit-name-${unitCount}"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="e.g., Sun"
                    oninput="updateUnitSlug(${unitCount})"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Slug <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    name="units[${unitCount}][slug]" 
                    id="unit-slug-${unitCount}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                    placeholder="sun"
                >
                <p class="text-xs text-gray-500 mt-1">URL-friendly identifier (auto-generated)</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    iCal Sources <span class="text-red-500">*</span>
                </label>
                <div id="sources-${unitCount}" class="space-y-2">
                    <div class="flex gap-2">
                        <input 
                            type="url" 
                            name="units[${unitCount}][ical_sources][0][url]" 
                            required
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                            placeholder="https://example.com/calendar.ics"
                        >
                        <button 
                            type="button" 
                            onclick="addSource(${unitCount})"
                            class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-sm">
                            +
                        </button>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-1">Calendar synchronization URLs (e.g., from Airbnb, Booking.com)</p>
            </div>
        `;
        
        container.appendChild(unitDiv);
    }

    function removeUnit(unitIndex) {
        const unitDiv = document.querySelector(`[data-unit-index="${unitIndex}"]`);
        if (unitDiv) {
            unitDiv.remove();
        }
        // Renumber remaining units
        renumberUnits();
    }

    function renumberUnits() {
        const units = document.querySelectorAll('#units-container > div');
        units.forEach((unit, index) => {
            const newNumber = index + 1;
            const h3 = unit.querySelector('h3');
            if (h3) {
                h3.textContent = `Unit #${newNumber}`;
            }
        });
    }

    function addSource(unitIndex) {
        const sourcesContainer = document.getElementById(`sources-${unitIndex}`);
        const sourceCount = sourcesContainer.querySelectorAll('input[type="url"]').length;
        
        const sourceDiv = document.createElement('div');
        sourceDiv.className = 'flex gap-2';
        sourceDiv.innerHTML = `
            <input 
                type="url" 
                name="units[${unitIndex}][ical_sources][${sourceCount}][url]" 
                required
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                placeholder="https://example.com/calendar.ics"
            >
            <button 
                type="button" 
                onclick="this.parentElement.remove()"
                class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-md text-sm">
                -
            </button>
        `;
        
        sourcesContainer.appendChild(sourceDiv);
    }

    function updateUnitSlug(unitIndex) {
        const nameInput = document.getElementById(`unit-name-${unitIndex}`);
        const slugInput = document.getElementById(`unit-slug-${unitIndex}`);
        
        // Only auto-update if slug hasn't been manually modified
        if (!slugInput.dataset.manuallyEdited) {
            const slug = nameInput.value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            
            slugInput.value = slug;
        }
    }

    function submitUnitsForm() {
        const formData = new FormData(document.getElementById('units-form'));
        
        // Convert FormData to nested object structure
        const data = { units: [] };
        const unitsMap = {};
        
        for (const [key, value] of formData.entries()) {
            const match = key.match(/units\[(\d+)\]\[([^\]]+)\](?:\[(\d+)\])?\[?([^\]]*)\]?/);
            if (match) {
                const [, unitIndex, field, sourceIndex, subfield] = match;
                
                if (!unitsMap[unitIndex]) {
                    unitsMap[unitIndex] = { ical_sources: [] };
                }
                
                if (sourceIndex !== undefined) {
                    // It's an iCal source
                    if (!unitsMap[unitIndex].ical_sources[sourceIndex]) {
                        unitsMap[unitIndex].ical_sources[sourceIndex] = {};
                    }
                    unitsMap[unitIndex].ical_sources[sourceIndex][subfield] = value;
                } else {
                    // It's a unit field
                    unitsMap[unitIndex][field] = value;
                }
            }
        }
        
        // Convert map to array
        data.units = Object.values(unitsMap);
        
        submitStep(data);
    }

    // Add first unit on load
    addUnit();
</script>
