<!DOCTYPE html>
<html lang="en">
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
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-gray-900">
                        🏖️ Bokit
                    </h1>
                    @if(app()->environment('local'))
                        <span class="text-xs font-semibold bg-yellow-400 text-yellow-900 px-2 py-1 rounded">
                            LOCAL
                        </span>
                    @endif
                </div>
                
                <div class="flex items-center space-x-4">
                    @if(session()->has('wp_user'))
                        <span class="text-sm text-gray-600">
                            {{ session('wp_user')['name'] }}
                        </span>
                        <a href="{{ route('logout') }}" class="text-sm text-blue-600 hover:text-blue-800">
                            Logout
                        </a>
                    @elseif(auth()->check())
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600">
                                {{ auth()->user()->name }}
                            </span>
                            @if(auth()->user()->isAdmin())
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Admin</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-blue-600 hover:text-blue-800">
                                Logout
                            </button>
                        </form>
                    @endif
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
