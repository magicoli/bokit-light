@extends('layouts.app')

@section('title', __('app.user_account'))
@section('content')
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
@endsection
