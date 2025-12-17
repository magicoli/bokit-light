@extends('layouts.app')

@section('title', $unit->name . ' - ' . $unit->property->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-baseline gap-3">
            <span class="text-2xl text-gray-500">{{ $unit->property->name }}  /</span>
            <h1 class="text-3xl font-bold text-gray-900">{{ $unit->name }}</h1>
        </div>
    </div>

    <!-- Placeholder Content -->
    <div class="bg-white rounded-lg shadow-sm p-8 text-center">
       <div class="text-left">
            <ul class="text-sm text-blue-800 space-y-1">
                <li><strong>Property:</strong> {{ $unit->property->name }}</li>
                <li><strong>Website:</strong> <a href="{{ $unit->property->settings['url'] ?? '' }}" class="text-blue-600 hover:underline" target="_blank">{{ $unit->property->settings['url'] ?? '' }}</a></li>
                <li><strong>Description:</strong> {{ $unit->description }}</li>
            </ul>
        </div>

        @if(auth()->check() && (auth()->user()->isAdmin() || $unit->property->users()->where('users.id', auth()->id())->exists()))
        <div class="mt-6">
            <a href="{{ route('units.edit', [$unit->property, $unit]) }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Unit
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
