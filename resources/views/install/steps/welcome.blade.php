<div class="space-y-6">
    <div class="text-center mb-8">
        <h2 class="text-3xl font-bold text-dark mb-2">{{ config('app.name') }}</h2>
        <p class="text-secondary">{{ config('app.slogan') }}</p>
    </div>

    <div class="mb-6">
        <p class="text-dark mb-6">
            This wizard will help you create the initial configuration of your calendar system.
        </p>
    </div>

    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg space-y-2 text-blue-900">
        <h3 class="font-semibold">What will be installed:</h3>
        <ul class="text-sm list-disc ps-4">
            <li>Database tables</li>
            <li>Cache system</li>
            <li>Session management</li>
            <li>Calendar sync system</li>
        </ul>
        <h3 class="font-semibold">What will be configured:</h3>
        <ul class="text-sm list-disc ps-4">
            <li>Authentication system</li>
            <li>Initial admin user</li>
            <li>Initial properties and rental units</li>
        </ul>
    </div>


    <div class="text-center text-sm text-secondary">
        <p>Installation should take less than 5 minutes</p>
    </div>
</div>
