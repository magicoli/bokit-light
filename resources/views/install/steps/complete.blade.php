<div class="space-y-6">
    <div class="bg-green-50 border border-green-200 rounded-lg p-6">
        <div class="flex items-center">
            <svg class="w-12 h-12 text-green-600 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h2 class="text-2xl font-bold text-green-900">Installation Complete!</h2>
                <p class="text-green-700 mt-1">Your Bokit calendar management system is ready to use.</p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-light rounded-lg p-6">
        <h3 class="text-lg font-semibold text-dark mb-4">What's been configured:</h3>
        <ul class="space-y-2">
            <li class="flex items-start">
                <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-dark">Database structure created</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-dark">Authentication method configured</span>
            </li>
            @if(App\Support\Options::get('auth.method') === 'wordpress')
            <li class="flex items-start">
                <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-dark">Administrator account created</span>
            </li>
            @endif
            <li class="flex items-start">
                <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-dark">Properties and rental units configured</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-dark">Calendar synchronization sources added</span>
            </li>
        </ul>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-primary mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div class="flex-1">
                <h4 class="text-sm font-medium text-blue-900 mb-1">Next Steps</h4>
                <p class="text-sm text-blue-700">
                    Click the button below to access your calendar and start managing your rental calendar.
                </p>
            </div>
        </div>
    </div>

    <div class="flex justify-center pt-4">
        <button onclick="completeInstallation()" class="px-8 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-lg">
            Go to Calendar
        </button>
    </div>
</div>

<script>
async function completeInstallation() {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Finalizing...';

    try {
        const response = await fetch('/install/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        } else {
            btn.disabled = false;
            btn.textContent = 'Go to Calendar';
            alert('An error occurred. Please refresh the page and try again.');
        }
    } catch (error) {
        btn.disabled = false;
        btn.textContent = 'Go to Calendar';
        alert('Network error: ' + error.message);
    }
}
</script>
