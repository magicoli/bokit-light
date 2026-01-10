@extends('layouts.app')
@section('body-class', 'login login-page')

@section('title', __('app.login'))

@push('styles')
@vite('resources/css/login.css')
@endpush

@section('content')
{!! appBrandingHtml() !!}

@if($errors->any())
        <ul class="notices">
            @foreach($errors->all() as $error)
                <li class="notice notice-error">{{ $error }}</li>
            @endforeach
        </ul>
@endif

<form class="space-y-4" method="POST" action="/login">
    @csrf
    <fieldset>
        <label class="block text-dark text-sm font-bold mb-2" for="username">
            {{ __('app.username_or_email') }}
        </label>
        <input
            class="shadow appearance-none border rounded w-full py-2 px-3 text-dark leading-tight focus:outline-none focus:shadow-outline"
            id="username"
            name="username"
            type="text"
            required
            autofocus
        >
    </fieldset>
    <fieldset>
        <label class="block text-dark text-sm font-bold mb-2" for="password">
            {{ __('app.password') }}
        </label>
        <input
            class="shadow appearance-none border rounded w-full py-2 px-3 text-dark leading-tight focus:outline-none focus:shadow-outline"
            id="password"
            name="password"
            type="password"
            required
        >
    </fieldset>
    <fieldset>
        <label class="flex items-center">
            <input
                type="checkbox"
                name="remember"
                class="mr-2 leading-tight"
                checked
            >
            <span class="text-sm text-dark">
                {{ __('app.remember_me') }}
            </span>
        </label>
    </fieldset>
    <div class="buttons items-right text-right">
        <button class="button-primary bg-primary text-black button submit-button" type="submit">
            {{ __('app.sign_in') }}
        </button>
    </div>
</form>

@if(isset($authMessage) || isset($authDetails))
<div class="mt-4 text-center text-sm text-secondary">
    @if(isset($authMessage))
        <p>{{ $authMessage }}</p>
    @endif
    @if(isset($authDetails))
        <p class="text-xs text-secondary mt-1">{{ $authDetails }}</p>
    @endif
</div>
@endif
@endsection
