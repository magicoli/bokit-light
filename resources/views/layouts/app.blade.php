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
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600">
                                {{ auth()->user()->name }}
                            </span>
                            @if(auth()->user()->isAdmin())
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">{{ __('app.admin') }}</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-blue-600 hover:text-blue-800">
                                {{ __('app.logout') }}
                            </button>
                        </form>
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
                        {{ now()->format('l, F j, Y') }}
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
