@extends('layouts.app')
@vite('resources/css/admin.css')

@section('body-class', 'admin')

@section('sidebar-left')
    {{-- Admin menu for sidebar-left - DYNAMIC --}}
    <nav id="admin-menu" class="admin-menu">
        <h3 class="menu-title">
            {{ __('app.administration') }}
        </h3>

        {!! app(\App\Services\AdminMenuService::class)->menuHtml() !!}
    </nav>
@endsection
