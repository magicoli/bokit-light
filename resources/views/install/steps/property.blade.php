<div class="mb-6">
    <h2 class="text-xl font-semibold text-dark mb-2">Create Your Property</h2>
    <p class="text-secondary text-sm">
        A property represents your organization or business entity that owns rental units.
    </p>
</div>

<form id="property-form" onsubmit="event.preventDefault(); submitPropertyForm();" class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-dark mb-1">
            Property Name <span class="text-red-500">*</span>
        </label>
        <input
            type="text"
            name="name"
            id="property-name"
            required
            class="w-full px-3 py-2 border border-light rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="e.g., Gîtes Mosaïques"
            oninput="updateSlug()"
        >
        <p class="text-xs text-secondary mt-1">The name of your rental business or organization</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-dark mb-1">
            Slug <span class="text-red-500">*</span>
        </label>
        <input
            type="text"
            name="slug"
            id="property-slug"
            required
            class="w-full px-3 py-2 border border-light rounded-md focus:outline-none focus:ring-2 focus:ring-primary font-mono text-sm"
            placeholder="gites-mosaiques"
        >
        <p class="text-xs text-secondary mt-1">URL-friendly identifier (auto-generated from name, but you can customize it)</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-dark mb-1">
            Website URL <span class="text-red-500">*</span>
        </label>
        <input
            type="url"
            name="url"
            required
            class="w-full px-3 py-2 border border-light rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="https://www.example.com"
        >
        <p class="text-xs text-secondary mt-1">Your property's website URL</p>
    </div>

    <button
        type="submit"
        data-loading="Creating Property..."
        class="w-full bg-primary hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
        Create Property & Continue
    </button>
</form>

<script>
    function updateSlug() {
        const nameInput = document.getElementById('property-name');
        const slugInput = document.getElementById('property-slug');

        // Only auto-update if slug hasn't been manually modified
        if (!slugInput.dataset.manuallyEdited) {
            const slug = nameInput.value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Remove accents
                .replace(/[^a-z0-9\s-]/g, '') // Remove special chars
                .replace(/\s+/g, '-') // Spaces to hyphens
                .replace(/-+/g, '-') // Multiple hyphens to single
                .replace(/^-|-$/g, ''); // Trim hyphens

            slugInput.value = slug;
        }
    }

    // Mark slug as manually edited when user types in it
    document.getElementById('property-slug').addEventListener('input', function() {
        this.dataset.manuallyEdited = 'true';
    });

    function submitPropertyForm() {
        const formData = new FormData(document.getElementById('property-form'));
        const data = Object.fromEntries(formData.entries());
        submitStep(data);
    }
</script>
