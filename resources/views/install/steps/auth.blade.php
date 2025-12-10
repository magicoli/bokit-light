<div class="text-center mb-6">
    <div class="text-4xl mb-2">{{ config('app.logo') }}</div>
    <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
    <p class="text-sm text-gray-500">{{ config('app.slogan') }}</p>
</div>

<div class="mb-8">
    <h2 class="text-xl font-semibold mb-2">Configure Authentication</h2>
    <p class="text-gray-600 text-sm">Choose how users will authenticate to access your calendar.</p>
</div>

<form id="auth-form" onsubmit="event.preventDefault(); submitAuthForm();" class="space-y-6">
    <!-- Authentication Method Selection -->
    <div class="space-y-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Authentication Method</label>

        <!-- None Option -->
        <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 transition">
            <input type="radio" name="auth_method" value="none" class="mt-1 mr-3" checked onchange="updateConfigVisibility()">
            <div class="flex-1">
                <div class="font-medium">No Authentication</div>
                <div class="text-sm text-gray-600 mt-1">Anyone can access the calendar without logging in</div>
                <div class="mt-2 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                    <strong>⚠️ Warning:</strong> This is highly insecure! Use only for development or in trusted private networks.
                </div>
            </div>
        </label>

        <!-- WordPress Option -->
        <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 transition">
            <input type="radio" name="auth_method" value="wordpress" class="mt-1 mr-3" onchange="updateConfigVisibility()">
            <div class="flex-1">
                <div class="font-medium">WordPress Integration</div>
                <div class="text-sm text-gray-600 mt-1">Authenticate using an existing WordPress site</div>
            </div>
        </label>
    </div>

    <!-- WordPress Configuration (hidden by default) -->
    <div id="wordpress-config" class="hidden space-y-4 pl-7 border-l-4 border-blue-500">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                WordPress Site URL <span class="text-red-500">*</span>
            </label>
            <input
                type="url"
                name="wp_site_url"
                id="wp_site_url"
                placeholder="https://example.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <p class="text-xs text-gray-500 mt-1">The URL of your WordPress site (without trailing slash)</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Required Role <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="wp_required_role"
                id="wp_required_role"
                value="administrator"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <p class="text-xs text-gray-500 mt-1">WordPress role required to access the calendar</p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-800">
            <strong>Note:</strong> You'll need to install the Bokit WordPress plugin on your WordPress site for authentication to work.
        </div>
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end">
        <button
            type="submit"
            data-loading="Configuring..."
            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded focus:outline-none focus:shadow-outline disabled:opacity-50 disabled:cursor-not-allowed"
        >
            Continue
        </button>
    </div>
</form>

<script>
    function updateConfigVisibility() {
        const wordpressRadio = document.querySelector('input[value="wordpress"]');
        const wordpressConfig = document.getElementById('wordpress-config');
        const wpSiteUrl = document.getElementById('wp_site_url');
        const wpRequiredRole = document.getElementById('wp_required_role');

        if (wordpressRadio.checked) {
            wordpressConfig.classList.remove('hidden');
            wpSiteUrl.required = true;
            wpRequiredRole.required = true;
        } else {
            wordpressConfig.classList.add('hidden');
            wpSiteUrl.required = false;
            wpRequiredRole.required = false;
        }
    }

    function submitAuthForm() {
        const formData = new FormData(document.getElementById('auth-form'));
        const data = Object.fromEntries(formData.entries());
        submitStep(data);
    }
</script>
