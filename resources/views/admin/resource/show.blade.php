@extends('layouts.admin-resource')

@php
$resource_page = 'view';
@endphp

@section('resource-page', $resource_page)

@section('resource-body')
    {{-- TODO: Implement tabs/actions for object display --}}
    {{-- - View tab: Display object details --}}
    {{-- - Edit tab/button: Link to edit page --}}
    {{-- - Delete button: Confirm and delete --}}
    {{-- - Custom actions based on model --}}
    TODO: content view
@endsection

@section('debug-info')
    <h4>{{ __('admin.object_details') }}</h4>
    <pre>{{ print_r($model->toArray(), true) }}</pre>
@endsection
