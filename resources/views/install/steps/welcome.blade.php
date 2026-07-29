<div class="space-y-6">
    <div class="text-center mb-8">
        <h2 class="text-3xl font-bold text-dark mb-2">{{ config('app.name') }}</h2>
        <p class="text-secondary">{{ config('app.slogan') }}</p>
    </div>

    <div class="mb-6">
        <p class="text-dark mb-6">
            {{ __('install.welcome.intro') }}
        </p>
    </div>

    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg space-y-2 text-blue-900">
        <h3 class="font-semibold">{{ __('install.welcome.installed_title') }}</h3>
        <ul class="text-sm list-disc ps-4">
            @foreach(__('install.welcome.installed') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
        <h3 class="font-semibold">{{ __('install.welcome.configured_title') }}</h3>
        <ul class="text-sm list-disc ps-4">
            @foreach(__('install.welcome.configured') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>


    <div class="text-center text-sm text-secondary">
        <p>{{ __('install.welcome.duration') }}</p>
    </div>
</div>
