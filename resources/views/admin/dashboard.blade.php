@extends('layouts.admin')

@section('title', __('admin.dashboard'))
@section('body-class', 'admin admin-dashboard')

@section('content')
<div class="prose max-w-none">
    <p class="description">
        {{ __('app.not_implemented') }}
    </p>

    <div class="flex flex-wrap gap-4 w-full">
        <div class="card">
            <h3 class="card.title">{{ __('admin.bookings') }}</h3>
            <p class="card.body">{{ __('app.not_implemented') }}</p>
        </div>

        <div class="card">
            <h3 class="card.title">{{ __('admin.properties') }}</h3>
            <p class="card.body">{{ __('app.not_implemented') }}</p>
        </div>

        <div class="card">
            <h3 class="card.title">{{ __('admin.rental_units') }}</h3>
            <p class="card.body">{{ __('app.not_implemented') }}</p>
        </div>
    </div>
</div>
@endsection
