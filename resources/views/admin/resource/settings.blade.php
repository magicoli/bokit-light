@extends('layouts.admin')

@section('body-class')
    @parent resource-settings {{ $resource }}-settings
@endsection

@section('title', __('admin.settings_resource', [ "resource" => __('app.' . $resource)]))

@section('content')
    <p class="text-secondary">{{ __('Resource settings - TODO') }}</p>
    <a href="{{ route('admin.' . $resource . '.index') }}" class="btn">
        {{ __('Back to list') }}
    </a>
@endsection
