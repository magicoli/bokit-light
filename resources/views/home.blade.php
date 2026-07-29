@extends('layouts.app')

{{-- No title section on purpose: the layout then titles the document after the site and its
     slogan, which is what a home page is called. --}}
@section('body-class', 'home-page')

@push('styles')
@vite('resources/css/markdown.css')
@vite('resources/css/home.css')
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    @vite('resources/js/home.js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const readmeContent = @json($readme);
            const contentDiv = document.getElementById('readme-content');
            contentDiv.innerHTML = marked.parse(readmeContent);
        });
    </script>
@endpush

@section('content')
    {{-- Data, not markup: the script reads it and builds the surfaces it fades between. --}}
    <script type="application/json" id="wallpapers">@json($wallpapers)</script>

    <div class="glass">
        <div id="readme-content" class="prose prose-slate max-w-none">
            <!-- Markdown content will be rendered here -->
        </div>

        @auth
        <div class="mt-6 text-center">
            <a href="{{ route('calendar') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors">
                {{ __('app.go_to_calendar') }} →
            </a>
        </div>
        @endauth
    </div>
@endsection
