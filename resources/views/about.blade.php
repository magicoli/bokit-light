@extends('layouts.app')

@section('title', 'About Bokit')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm p-8">
        <div id="readme-content" class="prose prose-slate max-w-none">
            <!-- Markdown content will be rendered here -->
        </div>
    </div>
    
    @auth
    <div class="mt-6 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            Go to Dashboard →
        </a>
    </div>
    @endauth
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    /* Tailwind Typography-like styles for markdown */
    .prose {
        color: #374151;
        line-height: 1.75;
    }
    .prose h1 {
        font-size: 2.25em;
        font-weight: 800;
        margin-top: 0;
        margin-bottom: 0.8888889em;
        line-height: 1.1111111;
    }
    .prose h2 {
        font-size: 1.5em;
        font-weight: 700;
        margin-top: 2em;
        margin-bottom: 1em;
        line-height: 1.3333333;
    }
    .prose h3 {
        font-size: 1.25em;
        font-weight: 600;
        margin-top: 1.6em;
        margin-bottom: 0.6em;
        line-height: 1.6;
    }
    .prose p {
        margin-top: 1.25em;
        margin-bottom: 1.25em;
    }
    .prose code {
        background-color: #f3f4f6;
        padding: 0.2em 0.4em;
        border-radius: 0.25rem;
        font-size: 0.875em;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .prose pre {
        background-color: #1f2937;
        color: #f9fafb;
        padding: 1em;
        border-radius: 0.5rem;
        overflow-x: auto;
        margin-top: 1.7142857em;
        margin-bottom: 1.7142857em;
    }
    .prose pre code {
        background-color: transparent;
        padding: 0;
        color: inherit;
    }
    .prose ul, .prose ol {
        margin-top: 1.25em;
        margin-bottom: 1.25em;
        padding-left: 1.625em;
    }
    .prose li {
        margin-top: 0.5em;
        margin-bottom: 0.5em;
    }
    .prose a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
    }
    .prose a:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }
    .prose blockquote {
        font-style: italic;
        border-left: 4px solid #e5e7eb;
        padding-left: 1em;
        margin: 1.6em 0;
        color: #6b7280;
    }
    .prose table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2em;
        margin-bottom: 2em;
    }
    .prose th, .prose td {
        border: 1px solid #e5e7eb;
        padding: 0.5em 1em;
        text-align: left;
    }
    .prose th {
        background-color: #f9fafb;
        font-weight: 600;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const readmeContent = @json($readme);
        const contentDiv = document.getElementById('readme-content');
        contentDiv.innerHTML = marked.parse(readmeContent);
    });
</script>
@endsection
