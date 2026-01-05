<div class="space-y-6">
    <form id="authForm" class="space-y-6">
        <div class="space-y-4">
            <!-- WordPress Authentication -->
            <div class="border-2 rounded-lg p-4 cursor-pointer hover:border-primary transition-colors auth-option" data-auth="wordpress">
                <label class="flex items-start cursor-pointer">
                    <input type="radio" name="auth_method" value="wordpress" class="mt-1 mr-3" required>
                    <div class="flex-1">
                        <h3 class="font-semibold text-dark mb-1">WordPress Authentication</h3>
                        <p class="text-sm text-secondary mb-3">
                            Use an existing WordPress site to manage users and permissions.
                        </p>
                        
                        <div class="wordpress-options hidden space-y-3 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-dark mb-1">
                                    WordPress Site URL <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="url" 
                                    name="wp_site_url" 
                                    class="w-full px-3 py-2 border border-light rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="https://www.example.com"
                                >
                                <p class="text-xs text-secondary mt-1">The Bokit WordPress plugin must be installed on this site</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-dark mb-1">
                                    Required WordPress Role <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    name="wp_required_role" 
                                    value="administrator"
                                    class="w-full px-3 py-2 border border-light rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="administrator"
                                >
                                <p class="text-xs text-secondary mt-1">Minimum WordPress role required to access Bokit</p>
                            </div>
                        </div>
                    </div>
                </label>
            </div>

            <!-- No Authentication -->
            <div class="border-2 rounded-lg p-4 cursor-pointer hover:border-primary transition-colors auth-option" data-auth="none">
                <label class="flex items-start cursor-pointer">
                    <input type="radio" name="auth_method" value="none" class="mt-1 mr-3" required>
                    <div class="flex-1">
                        <h3 class="font-semibold text-dark mb-1">No Authentication</h3>
                        <p class="text-sm text-secondary">
                            Open access without authentication. Suitable for private networks or development environments.
                        </p>
                        <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded p-3">
                            <p class="text-xs text-yellow-800">
                                <strong>Warning:</strong> Anyone who can access this URL will have full access to your calendar data. 
                                Only use this on a secure, private network.
                            </p>
                        </div>
                    </div>
                </label>
            </div>
        </div>
    </form>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-primary mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div class="flex-1">
                <p class="text-sm text-blue-700">
                    Select how users will authenticate to access the application.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radioButtons = document.querySelectorAll('input[name="auth_method"]');
    const wordpressOptions = document.querySelector('.wordpress-options');
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'wordpress') {
                wordpressOptions.classList.remove('hidden');
                wordpressOptions.querySelectorAll('input').forEach(input => {
                    input.required = true;
                });
            } else {
                wordpressOptions.classList.add('hidden');
                wordpressOptions.querySelectorAll('input').forEach(input => {
                    input.required = false;
                });
            }
        });
    });
    
    // Click on div = click on radio
    document.querySelectorAll('.auth-option').forEach(option => {
        option.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        });
    });
});
</script>
