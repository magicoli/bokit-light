@extends('layouts.app')

{{-- No title section on purpose: the layout then titles the document after the site and its
     slogan, which is what a home page is called. --}}
@section('body-class', 'home-page')

@push('styles')
@vite('resources/css/markdown.css')
@vite('resources/css/home.css')
@endpush

@push('scripts')
    @vite('resources/js/home.js')
@endpush

@section('content')
    {{-- Data, not markup: the script reads it and builds the surfaces it fades between. --}}
    <script type="application/json" id="wallpapers">@json($wallpapers)</script>

    <div class="glass">
        <div class="prose prose-slate max-w-none">
            {!! $content !!}
        </div>
    </div>
@endsection
