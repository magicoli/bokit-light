@extends('layouts.app')

@section('title', __('About :name', ['name' => config('app.name')]))

@section('styles')
@vite('resources/css/markdown.css')
{{-- <link rel="stylesheet" href="{{ asset('css/markdown.css') }}"> --}}
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const readmeContent = @json($readme);
            const contentDiv = document.getElementById('readme-content');
            contentDiv.innerHTML = marked.parse(readmeContent);
        });
    </script>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm p-8">
        <div id="readme-content" class="prose prose-slate max-w-none">
            <!-- Markdown contenzt will be rendered here -->
        </div>
    </div>

    @auth
    <div class="mt-6 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            {{ __('app.go_to_dashboard') }} →
        </a>
    </div>
    @endauth
</div>

@endsection
