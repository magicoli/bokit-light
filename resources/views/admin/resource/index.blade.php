@extends('layouts.admin')

@section('body-class')
    @parent resource-index {{ $resource }}-index
@endsection

@section('title', __('admin.' . $resource))

@section('content')
    {{-- Index redirects to list by default --}}
    @include('admin.resource.list')
@endsection
