@extends('layouts.admin')

@section('body-class', 'admin resource-settings')

@section('title', __('admin.settings') . ' - ' . __('admin.' . $resource))

@section('content')
    <div class="admin-page-header">
        <h1>{{ __('admin.' . $resource) }} - {{ __('admin.settings') }}</h1>
    </div>

    <div class="admin-content">
        <p class="text-gray-500">{{ __('Resource settings - TODO') }}</p>
        <a href="{{ route('admin.' . $resource . '.index') }}" class="btn">
            {{ __('Back to list') }}
        </a>
    </div>
@endsection
