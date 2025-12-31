@extends('layouts.admin')
@section('body-class')
    @parent resource-list {{ $resource }}-list
@endsection

@section('title', __('admin.' . $resource))

@section('styles')
@vite('resources/css/list.css')
@endsection

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
    @if(method_exists($model, 'list'))
        {!! $model->list()->render() !!}
    @else
        {{ __('app.method_not_implemented') }}
    @endif
@else
    {{ __('app.model_not_set') }}
@endif

@endsection
