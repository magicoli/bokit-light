<div class="text-center mb-8">
    <div class="text-5xl mb-3">{{ config('app.logo') }}</div>
    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ config('app.name') }}</h1>
    <p class="text-gray-600">{{ config('app.slogan') }}</p>
</div>

<div class="text-center mb-8">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>
    
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Installation Complete!</h2>
    <p class="text-gray-600 mb-6">
        Your calendar system is now ready to use.
    </p>
</div>

<div class="mb-6 p-4 bg-blue-50 rounded-lg">
    <h3 class="font-semibold text-blue-900 mb-2">What's installed:</h3>
    <ul class="text-sm text-blue-800 space-y-1">
        <li>✓ Database structure created</li>
        <li>✓ Authentication configured</li>
        <li>✓ Administrator account created</li>
        <li>✓ System ready for use</li>
    </ul>
</div>

<div class="text-center">
    <button
        onclick="window.location.href = '/'"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
        Go to Dashboard
    </button>
</div>
