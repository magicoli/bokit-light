<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Bokit - Calendar Manager')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- App Styles -->
    @vite('resources/css/app.css')
    @yield('styles')

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @yield('scripts')

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="nav-main">
        <div class="nav-container">
            <div class="nav-inner">
                <a href="{{ auth()->check() ? route('calendar') : route('home') }}" class="nav-branding">
                    <h1 class="nav-logo">
                        🏖️ Bokit
                    </h1>
                    @if(app()->environment('local'))
                        <span class="badge-env">
                            LOCAL
                        </span>
                    @endif
                </a>

                <div class="main-menu">
                    <!-- About page -->
                    <a href="{{ route('about') }}" class="nav-link">
                        {{ __('app.about') }}
                    </a>

                    <!-- Properties menu (direct link for now) -->
                    @if(auth()->check())
                        <!-- Calendar menu (direct link for now) -->
                        <a href="{{ route('calendar') }}" class="nav-link">
                            {{ __('app.calendar') }}
                        </a>

                        <!-- Properties menu (direct link for now) -->
                        <a href="{{ route('properties.index') }}" class="nav-link">
                            {{ __('app.properties') }}
                        </a>
                    @endif
                </div>

                <div class="nav-actions">
                    @if(auth()->check())
                        <!-- Admin menu (visible only for admins) -->
                        @if(auth()->user()->isAdmin())
                            <div class="dropdown" x-data="{ open: false }">
                                <button @click="open = !open"
                                        @click.away="open = false"
                                        class="dropdown-button">
                                    <span class="badge-admin">{{ __('app.admin') }}</span>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div x-show="open"
                                     x-cloak
                                     class="dropdown-menu">
                                    <a href="{{ route('admin.settings') }}" class="dropdown-item">
                                        {{ __('app.admin_settings') }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        <!-- User menu -->
                        <div class="dropdown" x-data="{ open: false }">
                            <button @click="open = !open"
                                    @click.away="open = false"
                                    class="dropdown-button">
                                <span>{{ auth()->user()->name }}</span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="open"
                                 x-cloak
                                 class="dropdown-menu">
                                <a href="{{ route('user.settings') }}" class="dropdown-item">
                                    {{ __('app.user_settings') }}
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item-button">
                                        {{ __('app.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <!-- Login link for guests -->
                        <a href="{{ route('login') }}" class="nav-login">
                            {{ __('app.login') }}
                        </a>
                    @endif

                    <span class="nav-date">
                        {{ now()->isoFormat('dddd LL') }}
                    </span>

                    <!-- Language switcher -->
                    <div class="locale-switcher">
                        <a href="{{ route('locale.change', 'en') }}"
                           class="locale-link {{ app()->getLocale() === 'en' ? 'active' : '' }}">
                            EN
                        </a>
                        <a href="{{ route('locale.change', 'fr') }}"
                           class="locale-link {{ app()->getLocale() === 'fr' ? 'active' : '' }}">
                            FR
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </nav>

    <main class="w-full px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>
</body>
</html>
