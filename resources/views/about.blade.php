@extends('layouts.app')

@section('title', __('app.about'))

@push('styles')
@vite('resources/css/markdown.css')
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const readmeContent = @json($readme);
            const contentDiv = document.getElementById('readme-content');
            contentDiv.innerHTML = marked.parse(readmeContent);
        });
    </script>
@endpush

@section('content')
    <div id="readme-content" class="prose prose-slate max-w-none">
        <!-- Markdown contenzt will be rendered here -->
    </div>

    @auth
    <div class="mt-6 text-center">
        <a href="{{ route('calendar') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors">
            {{ __('app.go_to_calendar') }} →
        </a>
    </div>
    @endauth
@endsection
