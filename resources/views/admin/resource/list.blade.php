@extends('layouts.admin')
@section('body-class')
    @parent resource-list
@endsection

@section('title', __('admin.' . $resource))

@section('content')
    <div class="admin-page-header">
        <h1>{{ __('admin.' . $resource) }}</h1>
    </div>

    <div class="admin-content">
        <p class="text-gray-500">{{ __('List view - TODO') }}</p>
        @if(Route::has('admin.' . $resource . '.create'))
            <a href="{{ route('admin.' . $resource . '.create') }}" class="btn btn-primary">
                {{ __('admin.add') }}
            </a>
        @endif
    </div>
@endsection
