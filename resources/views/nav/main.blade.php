        <div class="nav-container">
            <div class="nav-inner">
                <a href="{{ auth()->check() ? route('calendar') : route('home') }}">
                    <h1 class="nav-branding">
                        <div class="w-12 h-12">{!! appLogoHtml("") !!}</div>
                        <div class="app-title">
                            {{ config('app.name', 'Bokit') }}
                        </div>
                    </h1>
                </a>

                <!-- Desktop menu -->
                <div class="main-menu">
                    <!-- About page -->
                    <a href="{{ route('about') }}" class="nav-link">
                        {{ __('app.about') }}
                    </a>
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
                        <!-- Calendar menu -->
                        @if(user_can('property_manager'))
                            <a href="{{ route('calendar') }}" class="nav-link badge-manage">
                                {{ __('app.calendar') }}
                            </a>

                        <!-- Admin menu (visible for users who can manage properties) -->
                        <div class="dropdown"
                             x-data="{ open: false }"
                             @mouseenter="open = true"
                             @mouseleave="open = false">
                            <a href="{{ route('admin.dashboard') }}"
                               class="dropdown-button nav-link badge-admin">
                                <span>{{ __('app.admin') }}</span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </a>
                            <div x-show="open"
                                 x-cloak
                                 class="dropdown-menu">
                                <a href="{{ route('properties') }}" class="dropdown-item">
                                    {{ __('app.properties') }}
                                </a>
                                <a href="{{ route('rates') }}" class="dropdown-item">
                                    {{ __('rates.menu') }}
                                </a>
                            </div>
                        </div>
                        @endif

                        <!-- User menu -->
                        <div class="dropdown"
                             x-data="{ open: false }"
                             @mouseenter="open = true"
                             @mouseleave="open = false">
                            <a href="{{ route('dashboard') }}"
                               class="dropdown-button">
                                <span>{{ auth()->user()->name }}</span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </a>
                            <div x-show="open"
                                 x-cloak
                                 class="dropdown-menu">
                                <a href="{{ route('user.settings') }}" class="dropdown-item">
                                    {{ __('app.user_account') }}
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
                        @if(user_can('property_manager'))
                        <a href="{{ route('calendar') }}" class="nav-link">
                            {{ __('app.calendar') }}
                        </a>
                        @endif
                    @endif
                </div>

                @if(auth()->check())
                    <!-- User section -->
                    <div class="menu-section">
                        <div class="menu-title">{{ auth()->user()->name }}</div>
                        <a href="{{ route('user.settings') }}" class="nav-link">
                            {{ __('app.user_account') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav-link">
                                {{ __('app.logout') }}
                            </button>
                        </form>
                    </div>

                    <!-- Admin section (if user can manage properties) -->
                    @if(user_can('property_manager'))
                        <div class="menu-section">
                            <div class="menu-title">{{ __('app.admin') }}</div>
                            <a href="{{ route('admin.dashboard') }}" class="nav-link">
                                {{ __('app.dashboard') }}
                            </a>
                            <a href="{{ route('properties') }}" class="nav-link">
                                {{ __('app.properties') }}
                            </a>
                            <a href="{{ route('rates') }}" class="nav-link">
                                {{ __('rates.menu') }}
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
