@extends('layouts.admin')

@section('body-class')
    @parent resource-list {{ $resource }}-list
@endsection

@section('title', __('admin.' . $resource))
@section('action-links')
    @if(Route::has('admin.' . $resource . '.create'))
        <a href="{{ route('admin.' . $resource . '.create') }}" class="btn btn-primary">
            {!! icon('plus') !!}
            {{ __('admin.add') }}
        </a>
    @endif
@endsection

@section('content')

@if($model)
    <p class="debug">Result of: $model->list()->render()</p>
    <div class="bg-gray-100 p-4 border border-gray-300 rounded">
    {!! $model->list()->render() !!}
    </div>
    <p class="text-gray-500">
        DEBUG Raw result
    <div class="bg-pink-100 p-4">
        {{ $model->list()->render() }}
    </div>
    END DEBUG
    </p>
@else
    {{ __('No model found') }}
@endif

@endsection
