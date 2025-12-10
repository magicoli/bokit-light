@extends('install.layout')

@section('title', $step['title'])
@section('container-width', $step['name'] === 'auth' ? 'max-w-2xl' : 'max-w-md')

@section('content')
    <div id="step-container">
        @include('install.steps.' . $step['view'], ['step' => $step])
    </div>
@endsection

@section('scripts')
<script>
    async function submitStep(formData = {}) {
        const container = document.getElementById('step-container');
        
        // Show loading state
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const loadingText = submitBtn.dataset.loading || 'Processing...';
            submitBtn.dataset.originalText = submitBtn.textContent;
            submitBtn.textContent = loadingText;
        }

        try {
            const response = await fetch('/install', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                // Reload page to show next step
                window.location.reload();
            } else {
                // Show error
                showError(data.message || 'An error occurred');
                
                // Restore button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.dataset.originalText;
                }
            }
        } catch (error) {
            showError('Network error: ' + error.message);
            
            // Restore button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.originalText;
            }
        }
    }

    function showError(message) {
        let errorDiv = document.getElementById('error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'error-message';
            errorDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded';
            document.getElementById('step-container').prepend(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    }
</script>
@endsection
