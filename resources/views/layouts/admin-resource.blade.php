@extends('layouts.admin')

@php
$displayName = $displayName
    ?? Str::singular(__('admin.' . $resource)) . ' #' . $model->id;
@endphp

@section('body-class')
@parent {{ $resource_page }}-page {{ $resource_page }}-{{ $resource }} {{ $resource_page }}-{{ $resource }}-{{ $model->id }}
@endsection

{{-- Page title should include name of the object edited, not the resource class name --}}
@section('title', __(
    'forms.' . $resource_page . '_name',
    ['name' => $displayName]
))

@section('content')
<div class="card">
    <div class="card-header">
        <ul class="object-actions flex flex-row gap-4">
            <li><a href="{{ route('admin.' . $resource . '.show', $model->id) }}" class="btn btn-primary">
                {{ __('forms.view') }}
            </a></li>
            <li><a href="{{ route('admin.' . $resource . '.edit', $model->id) }}" class="btn btn-primary">
                {{ __('forms.edit') }}
            </a></li>
            {{-- <li><a href="{{ route('admin.' . $resource . '.delete', $model->id) }}" class="btn btn-primary">
                {{ __('forms.delete') }}
            </a></li> --}}

            <a href="{{ route('admin.' . $resource . '.list') }}" class="btn btn-secondary">
                {{ __('forms.list') }}
            </a>
        </ul>
    </div>
    <div class="card-body">
        @yield('resource-body')
    </div>
</div>
@endsection
