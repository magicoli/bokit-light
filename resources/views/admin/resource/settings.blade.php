@extends('layouts.admin')

@section('body-class')
    @parent resource-settings {{ $resource }}-settings
@endsection

@section('title', __('admin.settings') . ' - ' . __('admin.' . $resource))
@section('subtitle', 'La pêche (debug)')

@section('content')
    La pêche
    <p class="text-gray-500">{{ __('Resource settings - TODO') }}</p>
    <a href="{{ route('admin.' . $resource . '.index') }}" class="btn">
        {{ __('Back to list') }}
    </a>
@endsection
