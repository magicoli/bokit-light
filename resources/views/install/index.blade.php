<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Install {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full">
            <div class="text-center mb-8">
                <div class="text-5xl mb-3">{{ config('app.logo') }}</div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ config('app.name') }}</h1>
                <p class="text-gray-600">{{ config('app.slogan') }}</p>
            </div>

            <div id="install-form">
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
                    onclick="install()"
                    id="install-btn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
                    Install {{ config('app.name') }}
                </button>
            </div>

            <div id="install-progress" class="hidden">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                    <p class="text-gray-600">Installing...</p>
                </div>
            </div>

            <div id="install-success" class="hidden">
                <div class="text-center">
                    <div class="text-green-600 text-5xl mb-4">✓</div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Installation Complete!</h2>
                    <p class="text-gray-600 mb-4">Redirecting to dashboard...</p>
                </div>
            </div>

            <div id="install-error" class="hidden">
                <div class="text-center">
                    <div class="text-red-600 text-5xl mb-4">✗</div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Installation Failed</h2>
                    <p class="text-red-600 text-sm mb-4" id="error-message"></p>
                    <button
                        onclick="location.reload()"
                        class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                        Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function install() {
            const form = document.getElementById('install-form');
            const progress = document.getElementById('install-progress');
            const success = document.getElementById('install-success');
            const error = document.getElementById('install-error');

            form.classList.add('hidden');
            progress.classList.remove('hidden');

            try {
                const response = await fetch('/install/run', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                if (data.success) {
                    progress.classList.add('hidden');
                    success.classList.remove('hidden');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                progress.classList.add('hidden');
                error.classList.remove('hidden');
                document.getElementById('error-message').textContent = err.message;
            }
        }
    </script>
</body>
</html>
