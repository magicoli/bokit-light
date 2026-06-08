@if(auth()->check() && user_can('property_manager'))
    <a href="{{ route('calendar') }}" class="nav-link badge-manage">
        {{ __('app.calendar') }}
    </a>

    <div class="dropdown"
         x-data="{ open: false }"
         @mouseenter="open = true"
         @mouseleave="open = false">
        <a href="/admin"
           class="dropdown-button nav-link badge-admin">
            <span>{{ __('app.admin') }}</span>
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </a>
        <div x-show="open"
             x-cloak
             class="dropdown-menu">
            <a href="{{ route('admin.dashboard') }}" class="dropdown-item">
                {{ __('app.admin_legacy') }}
            </a>
            <a href="{{ route('properties') }}" class="dropdown-item">
                {{ __('app.properties') }} (legacy)
            </a>
            <a href="{{ route('rates') }}" class="dropdown-item">
                {{ __('rates.menu') }} (legacy)
            </a>
        </div>
    </div>
@endif
