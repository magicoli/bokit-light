@extends('layouts.admin')

@section('body-class')
    @parent resource-edit {{ $resource }}-edit
@endsection

@section('title', __('Edit') . ' - ' . __('admin.' . $resource))

@section('content')
    <div class="admin-page-header">
        <h1>{{ __('Edit') }} {{ __('admin.' . $resource) }}</h1>
    </div>

    <div class="admin-content">
        <p class="text-gray-500">{{ __('Form edition - TODO') }}</p>
        <a href="{{ route('admin.' . $resource . '.index') }}" class="btn">
            {{ __('Back to list') }}
        </a>
    </div>
@endsection
