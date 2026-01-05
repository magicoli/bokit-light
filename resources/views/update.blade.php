<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update - Bokit</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-8">
            <div class="text-center mb-6">
                <div class="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-dark mb-2">Update Required</h1>
                <p class="text-secondary text-sm">Database needs to be updated (local mode)</p>
            </div>

            <div id="status" class="mb-6 hidden">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary mr-3"></div>
                        <p class="text-blue-800 text-sm">Updating...</p>
                    </div>
                </div>
            </div>

            <div id="success" class="mb-6 hidden">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <p class="text-green-800 text-sm font-medium">Update completed!</p>
                    </div>
                </div>
            </div>

            <div id="error" class="mb-6 hidden">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800 text-sm font-medium">Update failed</p>
                    <p id="error-message" class="text-red-700 text-sm mt-1"></p>
                </div>
            </div>

            @if($count > 0)
            <div class="mb-6">
                <h2 class="text-sm font-medium text-dark mb-2">Pending:</h2>
                <ul class="text-sm text-secondary space-y-1">
                    @foreach($pendingMigrations as $migration)
                    <li class="truncate">• {{ $migration['file'] }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <button
                id="update-btn"
                onclick="runUpdate()"
                class="w-full bg-primary text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700"
            >
                Run Update
            </button>

            <a
                id="continue-btn"
                href="/"
                class="hidden w-full block text-center bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 mt-4"
            >
                Continue
            </a>
        </div>
    </div>

    <script>
        async function runUpdate() {
            const statusDiv = document.getElementById('status');
            const successDiv = document.getElementById('success');
            const errorDiv = document.getElementById('error');
            const errorMessage = document.getElementById('error-message');
            const updateBtn = document.getElementById('update-btn');
            const continueBtn = document.getElementById('continue-btn');

            statusDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');
            statusDiv.classList.remove('hidden');
            updateBtn.disabled = true;

            try {
                const response = await fetch('/update/execute', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await response.json();
                statusDiv.classList.add('hidden');

                if (data.success) {
                    successDiv.classList.remove('hidden');
                    updateBtn.classList.add('hidden');
                    continueBtn.classList.remove('hidden');
                } else {
                    errorDiv.classList.remove('hidden');
                    errorMessage.textContent = data.message;
                    updateBtn.disabled = false;
                }
            } catch (error) {
                statusDiv.classList.add('hidden');
                errorDiv.classList.remove('hidden');
                errorMessage.textContent = 'Error: ' + error.message;
                updateBtn.disabled = false;
            }
        }
    </script>
</body>
</html>
