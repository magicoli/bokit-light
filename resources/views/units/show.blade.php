@extends('layouts.app')

@section('title', $unit->name . ' - ' . $unit->property->name)

@section('styles')
@vite('resources/css/properties.css')
@endsection

@section('content')
<div class="unit-show-container">
    <!-- Header -->
    <div class="unit-show-header">
        <div class="title-row">
            <span class="property-name">{{ $unit->property->name }} /</span>
            <h1 class="unit-name">{{ $unit->name }}</h1>
        </div>
    </div>

    <!-- Content -->
    <div class="unit-content">
        <div class="description">
            <p>{{ $unit->description }}</p>
            <p class="property-info">
                @if(!empty($unit->property->settings['url']))
                {{ __('app.website') }}: <a href="{{ $unit->property->settings['url'] }}" target="_blank">{{ $unit->property->name }}</a>
                @else
                {{ __('app.property') }}: {{ $unit->property->name }}
                @endif
            </p>
        </div>

        @if(auth()->check() && (user_can('super_admin') || $unit->property->users()->where('users.id', auth()->id())->exists()))
        <div class="edit-action">
            <a href="{{ route('units.edit', [$unit->property, $unit]) }}"
               class="edit-button">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                {{ __('app.edit_unit') }}
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
