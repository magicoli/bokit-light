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
    @vite('resources/css/app.css')
    @yield('styles')

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @yield('scripts')

    <style>
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="nav-main" x-data="{ mobileMenuOpen: false }">
    @include('nav.main')
    </nav>

    <div id="content-wrapper">
        <sidebar>
            {{-- Not implemented yet --}}
        </sidebar>


        <main id="main" class="">
            <header class="header">
                <h1 class="title">@yield('title')</h1>
                <p class="subtitle">@yield('subtitle')</p>
            </header>

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <div class="content">
                @yield('content')
            </div>
        </main>
        <sidebar class="sidebar right-sidebar">
            {{-- Not implemented yet --}}
        </sidebar>
    </div>

    <footer class="footer hidden">
        <p class="copyright">&copy; {{ date('Y') }} {{ config('app.name', 'Bokit') }}</p>
    </footer>

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
