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
    {{-- @vite('resources/css/layout-flex.css') --}}
    @vite('resources/css/layout-grid.css')
    @vite('resources/css/app.css')
    @yield('styles')

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @yield('scripts')
</head>
<body class="@yield('body-class')">
    <div class="page-layout">
        <nav class="nav-main" x-data="{ mobileMenuOpen: false }">
        @include('nav.main')
        </nav>

        <div id="content-wrapper">
            <main id="main" class="main-column">
                <header class="header">
                    <h1 class="title">@yield('title')</h1>
                    <p class="subtitle">@yield('subtitle')</p>
                </header>

                {{-- session('success') is Deprecated, use notices instead, kept only until old code using is updated --}}
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                {{-- end of session('success') deprecated code --}}

                <!-- Flash notices -->
                {!! get_notices() !!}

                <div class="content">
                    @yield('content')
                </div>
            </main>

            <aside class="sidebar sidebar-left">
                @yield('sidebar-left')
            </aside>

            <aside class="sidebar sidebar-right">
                @yield('sidebar-right')
            </aside>
        </div>

        <footer class="footer">
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
