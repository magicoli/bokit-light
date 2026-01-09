@extends('layouts.app')

{{-- @section('title', __('user.dashboard')) --}}
@section('title', __('user.welcome_back_name', ['name' => auth()->user()->name]))

@section('content')
<div class="quicklinks buttons flex flex-wrap justify-center gap-4">
    @if(user_can('property_manager'))
        <a class="button badge-manage" href="{{ route('calendar') }}">{{ __('app.calendar') }}</a>
    @endif
    @if(auth()->user()->isAdmin())
        <a class="button badge-manage" href="{{ route('admin.properties.list') }}">{{ __('app.properties') }}</a>
        <a class="button badge-admin" href="{{ route('admin.dashboard') }}">{{ __('app.admin') }}</a>
    @endif
    <a class="button" href="{{ route('user.settings') }}">{{ __('app.account') }}</a>
</div>
@endsection
