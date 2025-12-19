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
    <nav class="nav-main" x-data="{ mobileMenuOpen: false }">
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

                <!-- Desktop menu -->
                <div class="main-menu">
                    <!-- About page -->
                    <a href="{{ route('about') }}" class="nav-link">
                        {{ __('app.about') }}
                    </a>

                    @if(auth()->check())
                        <!-- Calendar menu -->
                        <a href="{{ route('calendar') }}" class="nav-link">
                            {{ __('app.calendar') }}
                        </a>

                        <!-- Properties menu -->
                        <a href="{{ route('properties.index') }}" class="nav-link">
                            {{ __('app.properties') }}
                        </a>
                    @endif
                </div>

                <div class="nav-actions">
                    <span class="nav-date">
                        <span class="hidden lg:inline">{{ now()->isoFormat('dddd LL') }}</span>
                        <span class="lg:hidden">{{ now()->isoFormat('ddd D/M/YY') }}</span>
                    </span>

                    <!-- Hamburger button (mobile + tablet) -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="hamburger-button">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
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

            <!-- Mobile menu overlay -->
            <div x-show="mobileMenuOpen"
                 x-cloak
                 @click.away="mobileMenuOpen = false"
                 class="mobile-menu">
                
                <!-- Main navigation -->
                <div class="menu-section main-nav">
                    <a href="{{ route('about') }}" class="nav-link">
                        {{ __('app.about') }}
                    </a>

                    @if(auth()->check())
                        <a href="{{ route('calendar') }}" class="nav-link">
                            {{ __('app.calendar') }}
                        </a>

                        <a href="{{ route('properties.index') }}" class="nav-link">
                            {{ __('app.properties') }}
                        </a>
                    @endif
                </div>

                @if(auth()->check())
                    <!-- User section -->
                    <div class="menu-section">
                        <div class="menu-section-title">{{ auth()->user()->name }}</div>
                        <a href="{{ route('user.settings') }}" class="nav-link">
                            {{ __('app.user_settings') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav-link">
                                {{ __('app.logout') }}
                            </button>
                        </form>
                    </div>

                    <!-- Admin section (if admin) -->
                    @if(auth()->user()->isAdmin())
                        <div class="menu-section">
                            <div class="menu-section-title">{{ __('app.admin') }}</div>
                            <a href="{{ route('admin.settings') }}" class="nav-link">
                                {{ __('app.admin_settings') }}
                            </a>
                        </div>
                    @endif
                @else
                    <!-- Login for guests -->
                    <div class="menu-section">
                        <a href="{{ route('login') }}" class="nav-link">
                            {{ __('app.login') }}
                        </a>
                    </div>
                @endif

                <!-- Language switcher -->
                <div class="menu-section">
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
