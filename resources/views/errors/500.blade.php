@extends('layouts.app')

@section('title', __('Server Error'))

@section('content')
<div class="error-page">
    <h1 class="error-code">500</h1>
    <h2 class="error-title">{{ __('Server Error') }}</h2>
    <p class="error-message">
        {{ __('Something went wrong on our end. Please try again later.') }}
    </p>
    <div class="error-actions">
        <a href="{{ route('calendar') }}" class="button">
            {{ __('app.go_to_calendar') }}
        </a>
        <a href="{{ url('/') }}" class="button button-secondary">
            {{ __('Go Home') }}
        </a>
    </div>
</div>
@endsection
