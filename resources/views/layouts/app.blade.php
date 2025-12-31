<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
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
    @yield('styles')

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @yield('scripts')
</head>
<body class="@yield('body-class'){{ auth()->check() ? ' role-' . auth()->user()->getPrimaryRole() : '' }}">
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
                    {{-- Page content --}}
                    @yield('content')
                </div>
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
</body>
</html>
