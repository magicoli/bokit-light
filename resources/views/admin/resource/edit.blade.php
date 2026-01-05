@extends('layouts.admin-resource')

@php
$resource_page = 'edit';
@endphp

@section('resource-page', $resource_page)

@section('resource-body')
    {!! $formContent !!}
@endsection
