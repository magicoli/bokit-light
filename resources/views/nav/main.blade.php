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
                        @include('nav.top-links')

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
                                @if(Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item-button">
                                        {{ __('app.logout') }}
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    @else
                        <!-- Login link for guests, and only where logging in means something:
                             with auth.method on 'none' the route is not even declared. -->
                        @if(Route::has('login'))
                            <a href="{{ route('login') }}" class="nav-login">
                                {{ __('app.login') }}
                            </a>
                        @endif
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
                        @if(Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav-link">
                                {{ __('app.logout') }}
                            </button>
                        </form>
                        @endif
                    </div>

                    <!-- Admin section (if user can manage properties) -->
                    @if(user_can('property_manager'))
                        <div class="menu-section">
                            <div class="menu-title">
                                <a href="/admin" class="nav-link">{{ __('app.admin') }}</a>
                            </div>
                            <a href="{{ route('admin.dashboard') }}" class="nav-link">
                                {{ __('app.admin_legacy') }}
                            </a>
                            <a href="{{ route('properties') }}" class="nav-link">
                                {{ __('app.properties') }} (legacy)
                            </a>
                            <a href="{{ route('rates') }}" class="nav-link">
                                {{ __('rates.menu') }} (legacy)
                            </a>
                        </div>
                    @endif
                @else
                    <!-- Login for guests -->
                    <div class="menu-section">
                        @if(Route::has('login'))
                            <a href="{{ route('login') }}" class="nav-link">
                                {{ __('app.login') }}
                            </a>
                        @endif
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
