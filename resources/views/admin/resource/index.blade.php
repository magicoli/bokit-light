@extends('layouts.admin')

@section('body-class', 'admin resource-index')

@section('title', __('admin.' . $resource))

@section('content')
    {{-- Index view includes list by default --}}
    @include('admin.resource.list')
@endsection
