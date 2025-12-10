<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Installation') - Bokit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @yield('head')
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="@yield('container-width', 'max-w-md') w-full">
            <div class="bg-white rounded-lg shadow-lg p-8">
                @yield('content')
            </div>
        </div>
    </div>
    @yield('scripts')
</body>
</html>
