@extends('layouts.app')

@section('title', __('Page Not Found'))

@section('content')
<div class="error-page">
    <h1 class="error-code">404</h1>
    <h2 class="error-title">{{ __('Page Not Found') }}</h2>
    <p class="error-message">
        {{ __('The page you are looking for could not be found.') }}
    </p>
    <div class="error-actions">
        <a href="{{ route('calendar') }}" class="button">
            {{ __('app.go_to_calendar') }}
        </a>
        <a href="{{ url()->previous() }}" class="button button-secondary">
            {{ __('Go Back') }}
        </a>
    </div>
</div>
@endsection
