<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Bokit - Calendar Manager')</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                    <h1 class="text-2xl font-bold text-gray-900">
                        🏖️ Bokit
                    </h1>
                    @if(app()->environment('local'))
                        <span class="text-xs font-semibold bg-yellow-400 text-yellow-900 px-2 py-1 rounded">
                            LOCAL
                        </span>
                    @endif
                </a>
                
                <div class="flex items-center space-x-4">
                    @if(session()->has('wp_user'))
                        <span class="text-sm text-gray-600">
                            {{ session('wp_user')['name'] }}
                        </span>
                        <a href="{{ route('logout') }}" class="text-sm text-blue-600 hover:text-blue-800">
                            {{ __('app.logout') }}
                        </a>
                    @elseif(auth()->check())
                        <!-- Admin menu (visible only for admins) -->
                        @if(auth()->user()->isAdmin())
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" 
                                        @click.away="open = false"
                                        class="flex items-center space-x-1 text-sm text-gray-700 hover:text-gray-900 focus:outline-none">
                                    <span class="px-2 py-1 rounded bg-red-100 text-red-800 font-semibold">{{ __('app.admin') }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div x-show="open" 
                                     x-cloak
                                     class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 border border-gray-200">
                                    <a href="{{ route('admin.settings') }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        {{ __('app.admin_settings') }}
                                    </a>
                                </div>
                            </div>
                        @endif
                        
                        <!-- User menu -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" 
                                    @click.away="open = false"
                                    class="flex items-center space-x-1 text-sm text-gray-700 hover:text-gray-900 focus:outline-none">
                                <span>{{ auth()->user()->name }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="open" 
                                 x-cloak
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 border border-gray-200">
                                <a href="{{ route('user.settings') }}" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    {{ __('app.user_settings') }}
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" 
                                            class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        {{ __('app.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Language switcher -->
                    <div class="flex items-center space-x-1 border-l border-gray-300 pl-4">
                        <a href="{{ route('locale.change', 'en') }}" 
                           class="text-xs px-2 py-1 rounded {{ app()->getLocale() === 'en' ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                            EN
                        </a>
                        <a href="{{ route('locale.change', 'fr') }}" 
                           class="text-xs px-2 py-1 rounded {{ app()->getLocale() === 'fr' ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                            FR
                        </a>
                    </div>
                    
                    <span class="text-sm text-gray-500">
                        {{ now()->isoFormat('dddd LL') }}
                    </span>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="w-full px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>
</body>
</html>
