<div class="text-center mb-8">
    <div class="text-5xl mb-3">{{ config('app.logo') }}</div>
    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ config('app.name') }}</h1>
    <p class="text-gray-600">{{ config('app.slogan') }}</p>
</div>

<div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-2">Ready to Install</h2>
    <p class="text-gray-600 text-sm">
        This will create the database structure and set up your calendar system.
    </p>
</div>

<div class="mb-6 p-4 bg-blue-50 rounded-lg">
    <h3 class="font-semibold text-blue-900 mb-2">What will be installed:</h3>
    <ul class="text-sm text-blue-800 space-y-1">
        <li>✓ Database tables</li>
        <li>✓ Cache system</li>
        <li>✓ Session management</li>
        <li>✓ Calendar sync system</li>
    </ul>
</div>

<button
    onclick="submitStep()"
    type="submit"
    data-loading="Installing..."
    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
    Begin Installation
</button>
