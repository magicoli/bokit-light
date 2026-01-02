@extends('layouts.admin')
@section('body-class')
    @parent resource-list {{ $resource }}-list
@endsection

@section('title', __('admin.' . $resource))

@section('styles')
@vite('resources/css/list.css')
@vite('resources/css/form.css')
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
