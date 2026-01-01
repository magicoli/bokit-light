@extends('layouts.app')

@section('title', __('app.unauthorized'))

@section('content')
<div class="error-page">
    <h1 class="error-code">403</h1>
    <h2 class="error-title">{{ __('app.unauthorized') }}</h2>
    <p class="error-message">
        {{ $exception->getMessage() ?: __('You do not have permission to access this page.') }}
    </p>
    <div class="error-actions">
        <a href="{{ route('calendar') }}" class="button">
            {{ __('app.go_to_calendar') }}
        </a>
        @if(auth()->check())
            <a href="{{ url()->previous() }}" class="button button-secondary">
                {{ __('Go Back') }}
            </a>
        @else
            <a href="{{ route('login') }}" class="button button-secondary">
                {{ __('app.login') }}
            </a>
        @endif
    </div>
</div>
@endsection
