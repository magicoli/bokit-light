@extends('layouts.admin')

@section('body-class')
    @parent resource-create {{ $resource }}-create
@endsection

@section('title', __('admin.add') . ' - ' . __('admin.' . $resource))

@section('content')
    <div class="admin-page-header">
        <h1>{{ __('admin.add') }} {{ __('admin.' . $resource) }}</h1>
    </div>

    <div class="admin-content">
        <p class="text-gray-500">{{ __('Form creation - TODO') }}</p>
        <a href="{{ route('admin.' . $resource . '.index') }}" class="btn">
            {{ __('Back to list') }}
        </a>
    </div>
@endsection
