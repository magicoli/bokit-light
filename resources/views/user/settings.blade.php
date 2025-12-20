@extends('layouts.app')

@section('title', __('app.user_account'))

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">{{ __('app.user_account') }}</h1>
        <p class="text-gray-600 mt-2">Manage your profile and preferences</p>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Profile Information</h2>
            <div class="space-y-2">
                <div>
                    <span class="text-sm font-medium text-gray-500">Name:</span>
                    <span class="text-sm text-gray-900 ml-2">{{ auth()->user()->name }}</span>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Email:</span>
                    <span class="text-sm text-gray-900 ml-2">{{ auth()->user()->email }}</span>
                </div>
            </div>
        </div>
        
        <p class="text-gray-500 text-sm">Additional user settings to be implemented</p>
    </div>
</div>
@endsection
