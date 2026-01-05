@extends('install.layout')

@section('title', $step['title'])
@section('container-width', $step['name'] === 'setup' ? 'max-w-5xl' : ($step['name'] === 'auth' ? 'max-w-2xl' : 'max-w-md'))

@section('content')
    <!-- Progress indicator -->
    <div class="mb-6">
        <div class="flex items-center justify-between text-sm text-secondary mb-2">
            <span>Step {{ $stepNumber }} of {{ $totalSteps }}</span>
            <span>{{ round(($stepNumber / $totalSteps) * 100) }}%</span>
        </div>
        <div class="w-full bg-light rounded-full h-2">
            <div class="bg-primary h-2 rounded-full transition-all duration-300" style="width: {{ ($stepNumber / $totalSteps) * 100 }}%"></div>
        </div>
    </div>

    <!-- Step title -->
    @if($step['name'] !== 'welcome')
    <h1 class="text-2xl font-bold text-dark mb-6">{{ $step['title'] }}</h1>
    @endif

    <div id="step-container">
        @include('install.steps.' . $step['view'], ['step' => $step])
    </div>

    <!-- Navigation buttons -->
    @if(!isset($step['no_process']) || !$step['no_process'])
    <div class="mt-6 flex justify-end">
        <button
            type="button"
            onclick="handleSubmit()"
            data-loading="Processing..."
            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
        >
            Continue
        </button>
    </div>
    @endif
@endsection

@section('scripts')
<script>
    async function handleSubmit() {
        const stepName = '{{ $step['name'] }}';
        let formData = {};

        // Collect form data based on step
        if (stepName === 'welcome') {
            formData = {};
        } else if (stepName === 'auth') {
            const form = document.getElementById('authForm');
            const data = new FormData(form);
            formData = Object.fromEntries(data.entries());
        } else if (stepName === 'admin') {
            const form = document.getElementById('adminForm');
            const data = new FormData(form);
            formData = Object.fromEntries(data.entries());
        } else if (stepName === 'setup') {
            const form = document.getElementById('setupForm');
            const data = new FormData(form);

            // Convert nested FormData to proper structure
            formData = { properties: {} };

            for (const [key, value] of data.entries()) {
                // Parse keys like properties[1][name], properties[1][units][1][name], properties[1][units][1][ical_sources][1][url]
                const propMatch = key.match(/properties\[(\d+)\]\[([^\]]+)\]$/);
                const unitMatch = key.match(/properties\[(\d+)\]\[units\]\[(\d+)\]\[([^\]]+)\]$/);
                const sourceMatch = key.match(/properties\[(\d+)\]\[units\]\[(\d+)\]\[ical_sources\]\[(\d+)\]\[([^\]]+)\]$/);

                if (sourceMatch) {
                    // iCal source field
                    const [, propId, unitId, sourceId, field] = sourceMatch;

                    if (!formData.properties[propId]) {
                        formData.properties[propId] = { units: {} };
                    }
                    if (!formData.properties[propId].units[unitId]) {
                        formData.properties[propId].units[unitId] = { ical_sources: {} };
                    }
                    if (!formData.properties[propId].units[unitId].ical_sources[sourceId]) {
                        formData.properties[propId].units[unitId].ical_sources[sourceId] = {};
                    }

                    formData.properties[propId].units[unitId].ical_sources[sourceId][field] = value;
                } else if (unitMatch) {
                    // Unit field
                    const [, propId, unitId, field] = unitMatch;

                    if (!formData.properties[propId]) {
                        formData.properties[propId] = { units: {} };
                    }
                    if (!formData.properties[propId].units[unitId]) {
                        formData.properties[propId].units[unitId] = { ical_sources: {} };
                    }

                    formData.properties[propId].units[unitId][field] = value;
                } else if (propMatch) {
                    // Property field
                    const [, propId, field] = propMatch;

                    if (!formData.properties[propId]) {
                        formData.properties[propId] = { units: {} };
                    }

                    formData.properties[propId][field] = value;
                }
            }

            // Convert objects to arrays
            formData.properties = Object.values(formData.properties).map(prop => ({
                ...prop,
                units: Object.values(prop.units).map(unit => ({
                    ...unit,
                    ical_sources: Object.values(unit.ical_sources)
                }))
            }));
        }

        await submitStep(formData);
    }

    async function submitStep(formData = {}) {
        const container = document.getElementById('step-container');

        // Show loading state
        const submitBtn = document.querySelector('button[onclick="handleSubmit()"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const loadingText = submitBtn.dataset.loading || 'Processing...';
            submitBtn.dataset.originalText = submitBtn.textContent;
            submitBtn.textContent = loadingText;
        }

        try {
            const response = await fetch('/install', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                // Reload page to show next step
                window.location.reload();
            } else {
                // Show error
                showError(data.message || 'An error occurred');

                // Restore button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.dataset.originalText;
                }
            }
        } catch (error) {
            showError('Network error: ' + error.message);

            // Restore button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.originalText;
            }
        }
    }

    function showError(message) {
        let errorDiv = document.getElementById('error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'error-message';
            errorDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded';
            document.getElementById('step-container').prepend(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');

        // Scroll to error
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
</script>
@endsection
