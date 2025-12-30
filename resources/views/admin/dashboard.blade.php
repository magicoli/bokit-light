@extends('layouts.app')

@section('title', __('app.admin_dashboard'))
@section('body-class', 'admin admin-dashboard')

@section('sidebar-left')
    @include('admin.partials.menu')
@endsection

@section('content')
<div class="prose max-w-none">
    <h1>{{ __('app.admin_dashboard') }}</h1>
    
    <div class="bg-blue-50 border border-blue-200 rounded p-4 not-prose">
        <p class="text-sm">
            📊 {{ __('app.dashboard_coming_soon') }}
        </p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6 not-prose">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-2">{{ __('app.bookings') }}</h3>
            <p class="text-3xl font-bold text-blue-600">-</p>
            <p class="text-sm text-gray-500">{{ __('app.placeholder_data') }}</p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-2">{{ __('app.properties') }}</h3>
            <p class="text-3xl font-bold text-green-600">-</p>
            <p class="text-sm text-gray-500">{{ __('app.placeholder_data') }}</p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-2">{{ __('app.units') }}</h3>
            <p class="text-3xl font-bold text-purple-600">-</p>
            <p class="text-sm text-gray-500">{{ __('app.placeholder_data') }}</p>
        </div>
    </div>
</div>
@endsection
