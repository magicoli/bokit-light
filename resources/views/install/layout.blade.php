<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('install.title')) - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // The wizard runs before anything is built, so it uses the CDN rather than the compiled
        // stylesheet — and the CDN knows nothing of the application's own colour names. Without
        // this, bg-primary paints nothing and the Continue button is white on white.
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f97316',
                        secondary: '#6b7280',
                        dark: '#1f2937',
                        light: '#e5e7eb',
                    },
                },
            },
        };
    </script>
    @yield('head')
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="@yield('container-width', 'max-w-md') w-full">
            <div class="flex justify-end gap-2 mb-3 text-sm">
                @foreach(['en' => 'English', 'fr' => 'Français'] as $locale => $label)
                    <a
                        href="{{ route('locale.change', $locale) }}"
                        class="px-2 py-1 rounded {{ app()->getLocale() === $locale ? 'bg-primary text-white' : 'text-secondary hover:text-primary' }}"
                    >{{ $label }}</a>
                @endforeach
            </div>

            <div class="bg-white rounded-lg shadow-lg p-8">
                {!! appBrandingHtml() !!}
                @yield('content')
            </div>
        </div>
    </div>
    @yield('scripts')
</body>
</html>
