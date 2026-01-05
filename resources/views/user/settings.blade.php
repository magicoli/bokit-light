@extends('layouts.app')

@section('title', __('app.user_account'))
@section('content')
    <h2 class="text-lg font-semibold text-dark mb-2">Profile Information</h2>
    <div class="space-y-2">
        <div>
            <span class="text-sm font-medium text-secondary">Name:</span>
            <span class="text-sm text-dark ml-2">{{ auth()->user()->name }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-secondary">Email:</span>
            <span class="text-sm text-dark ml-2">{{ auth()->user()->email }}</span>
        </div>
    </div>
@endsection
