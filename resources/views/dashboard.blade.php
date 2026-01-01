@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>{{ __('Dashboard') }}</h1>
        <p class="text-muted">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</p>
    </div>

    <div class="dashboard-content">
        <div class="dashboard-placeholder">
            <p>{{ __('Your personalized dashboard will appear here.') }}</p>
            
            <div class="quick-links">
                <h2>{{ __('Quick Links') }}</h2>
                <ul>
                    <li><a href="{{ route('calendar') }}">{{ __('Calendar') }}</a></li>
                    @if(user_can('property_manager'))
                        <li><a href="{{ route('admin.properties.list') }}">{{ __('My Properties') }}</a></li>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <li><a href="{{ route('admin.dashboard') }}">{{ __('Admin Dashboard') }}</a></li>
                    @endif
                    <li><a href="{{ route('user.settings') }}">{{ __('Settings') }}</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
