@extends('layouts.admin')
@section('body-class')
    @parent resource-index
@endsection

@section('title', __('admin.' . $resource))

@section('content')
    {{-- Index view includes list by default --}}
    @include('admin.resource.list')
@endsection
