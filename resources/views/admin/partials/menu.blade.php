{{-- Admin menu for sidebar-left - DYNAMIC --}}
<nav class="admin-menu space-y-2">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
        {{ __('app.administration') }}
    </h3>
    
    {{-- Dashboard --}}
    <a href="{{ route('admin.dashboard') }}" 
       class="block px-3 py-2 rounded {{ request()->routeIs('admin.dashboard') ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        📊 {{ __('app.dashboard') }}
    </a>
    
    {{-- Settings (admin only) --}}
    @can('admin')
    <a href="{{ route('admin.settings') }}" 
       class="block px-3 py-2 rounded {{ request()->routeIs('admin.settings') ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        ⚙️ {{ __('app.settings') }}
    </a>
    @endcan
    
    {{-- Dynamic resource sections from AdminResourceTrait --}}
    @php
        $adminResources = app(\App\Services\AdminMenuService::class)->getResources();
    @endphp
    
    @foreach($adminResources as $resource)
        <hr class="my-4 border-gray-200">
        
        <div class="space-y-1">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                {{ $resource['icon'] }} {{ $resource['label'] }}
            </h4>
            
            @foreach($resource['routes'] as $routeType)
                @php
                    $routeName = "admin.{$resource['resource_name']}.{$routeType}";
                    $isActive = request()->routeIs("admin.{$resource['resource_name']}.*");
                @endphp
                
                @if(Route::has($routeName))
                    <a href="{{ route($routeName) }}" 
                       class="block px-3 py-2 text-sm rounded {{ $isActive ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        {{ __("app.{$routeType}") }}
                    </a>
                @endif
            @endforeach
        </div>
    @endforeach
</nav>
