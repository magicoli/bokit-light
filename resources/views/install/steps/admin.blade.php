<div class="text-center mb-8">
    <div class="text-5xl mb-3">{{ config('app.logo') }}</div>
    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ config('app.name') }}</h1>
    <p class="text-gray-600">{{ config('app.slogan') }}</p>
</div>

<div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-2">Create Administrator Account</h2>
    <p class="text-gray-600 text-sm">
        This will be the first administrator account with full access to the system.
    </p>
</div>

@php
    $authMethod = \App\Support\Options::get('auth.method');
@endphp

@if($authMethod === 'wordpress')
    <div class="mb-6 p-4 bg-blue-50 rounded-lg">
        <h3 class="font-semibold text-blue-900 mb-2">WordPress Authentication</h3>
        <p class="text-sm text-blue-800">
            Log in with your WordPress credentials. You'll become the administrator of this calendar system.
        </p>
        <p class="text-sm text-blue-800 mt-2">
            <strong>Site:</strong> {{ \App\Support\Options::get('auth.wordpress.site_url') }}<br>
            <strong>Required role:</strong> {{ \App\Support\Options::get('auth.wordpress.required_role') }}
        </p>
    </div>

    <form id="admin-form" onsubmit="event.preventDefault(); submitAdminForm();" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Username <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                name="username" 
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="WordPress username"
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Password <span class="text-red-500">*</span>
            </label>
            <input 
                type="password" 
                name="password" 
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="WordPress password"
            >
        </div>

        <button
            type="submit"
            data-loading="Authenticating..."
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
            Create Admin & Continue
        </button>
    </form>
@else
    <div class="mb-6 p-4 bg-yellow-50 rounded-lg">
        <p class="text-sm text-yellow-800">
            <strong>⚠️ No Authentication:</strong> This information will identify you as the administrator but won't be used for login verification.
        </p>
    </div>

    <form id="admin-form" onsubmit="event.preventDefault(); submitAdminForm();" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Name <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                name="name" 
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Your full name"
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Email <span class="text-red-500">*</span>
            </label>
            <input 
                type="email" 
                name="email" 
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="your@email.com"
            >
        </div>

        <button
            type="submit"
            data-loading="Creating..."
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
            Create Admin & Continue
        </button>
    </form>
@endif

<script>
    function submitAdminForm() {
        const formData = new FormData(document.getElementById('admin-form'));
        const data = Object.fromEntries(formData.entries());
        submitStep(data);
    }
</script>
