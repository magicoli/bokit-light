<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @php
        // Read PWA mode from cookie set by JavaScript
        $isPWA = request()->cookie('pwa_standalone') === '1';
        @endphp
        @if($isPWA)
            @hasSection('title')
                @yield('title')
            @else
                {{ __('app.slogan') }}
            @endif
        @else
            @hasSection('title')
                @yield('title') - {{ config('app.name', 'Bokit') }}
            @else
                {{ config('app.name', 'Bokit') }} - {{ __('app.slogan') }}
            @endif
        @endif
    </title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#FDD389">

    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Bokit">
    <link rel="apple-touch-icon" href="/images/icons/apple-touch-icon.png">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- App Styles -->
    @vite('resources/css/layout-grid.css')
    @vite('resources/css/app.css')

    <!-- Bootstrap Icons for custom grip icon -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @livewireStyles
    @filamentStyles
    @yield('styles')
    @stack('styles')
    {{-- Dynamically added styles via addStyle() helper --}}
    @foreach($__pageStyles ?? [] as $style)
        @vite($style)
    @endforeach

    {{-- App scripts. Alpine is not loaded here: @livewireScripts ships its own instance at the
         end of the body, and a second one makes both fight over the same directives. --}}
    @vite('resources/js/app.js')

    @yield('scripts')
    @stack('scripts')
    {{-- Dynamically added scripts via addScript() helper --}}
    @foreach($__pageScripts ?? [] as $script)
        @vite($script)
    @endforeach

    {!! ToastMagic::styles() !!}
</head>
<body class="@yield('body-class') {{ user_classes() }}">
    <div id="page-layout">
        {{-- Main navigation --}}
        <nav x-data="{ mobileMenuOpen: false }">
            @include('nav.main')
        </nav>

        {{-- Main area: header + content + sidebars --}}
        <div id="main-area">
            <main>
                {{-- Page header (title, subtitle, breadcrumbs, etc.) --}}
                <header>
                    @if(!View::hasSection('title_display') || View::getSection('title_display') === 'default')
                        {{-- Case 1: Standard display (default) --}}
                        @hasSection('header')
                            @yield('header')
                        @else
                            @hasSection('title')
                                <h1>@yield('title')</h1>
                            @endif
                            @hasSection('subtitle')
                                <p class="subtitle">@yield('subtitle')</p>
                            @endif
                        @endif
                    @endif
                    {{-- Cases 2 & 3: header is empty, hidden by CSS :not(:has(*)) --}}
                </header>

                {{-- Flash notices --}}
                {!! get_notices() !!}

                {{-- Deprecated, use notices instead, kept only until old code using is updated --}}
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                {{-- end of session('success') deprecated code --}}

                {{-- Main content area --}}
                <div id="main-content">
                    {{ $slot ?? '' }}
                    {{-- Page content --}}
                    @yield('content')
                </div>

                @if(config('app.debug') == true)
                    @php
                    if(isset($exception) && $exception->getMessage()) {
                        debug_error("layout/app.blade.php exception", $exception);
                    }
                    @endphp
                    @hasSection('debug-info')
                        <div id="debug-info" class="debug-info">
                            <h3>Debug Information</h3>
                            @yield('debug-info')
                        </div>
                    @endif
                @endif
            </main>

            {{-- Left sidebar --}}
            <aside id="sidebar-left" class="sidebar">
                @yield('sidebar-left')
            </aside>

            {{-- Right sidebar --}}
            <aside id="sidebar-right" class="sidebar">
                @yield('sidebar-right')
            </aside>
        </div>

        {{-- Footer --}}
        <footer>
            <p class="copyright">&copy; {{ date('Y') }} {{ config('app.name', 'Bokit') }}</p>
        </footer>
    </div>

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .catch(error => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
    </script>
    {!! ToastMagic::scripts() !!}
    @livewireScripts
</body>
</html>
