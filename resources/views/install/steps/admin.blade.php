<?php
use App\Support\Options;
?><div class="space-y-6">
    <form id="adminForm" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-dark mb-1">
                WordPress Username <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="username"
                class="w-full px-3 py-2 border border-light rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                placeholder="your-username"
                required
                autocomplete="username"
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-dark mb-1">
                WordPress Password <span class="text-red-500">*</span>
            </label>
            <input
                type="password"
                name="password"
                class="w-full px-3 py-2 border border-light rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                required
                autocomplete="current-password"
            >
        </div>
    </form>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-primary mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div class="flex-1">
                <p class="text-sm text-blue-700 mb-2">
                    Log in with your WordPress credentials to create the first administrator account for Bokit.
                </p>
            </div>
        </div>

        <div class="mt-3">
            <h4 class="text-sm font-semibold text-dark mb-2">Requirements</h4>
            <ul class="text-sm text-dark space-y-1">
                <li class="flex items-start">
                    <svg class="w-4 h-4 text-secondary mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>You must already have an account on <strong><a href="{{ Options::get('auth.wordpress.site_url') }}">{{ Options::get('auth.wordpress.site_url') }}</a></strong>.</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-4 h-4 text-secondary mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Your account must have <strong>{{ Options::get('auth.wordpress.required_role') }}</strong> role (configured in previous step)</span>
                </li>
            </ul>
        </div>
    </div>
</div>
