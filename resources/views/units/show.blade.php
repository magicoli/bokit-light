@extends('layouts.app')

@section('title', $unit->name . ' - ' . $unit->property->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-baseline gap-3">
            <span class="text-3xl">{{ $unit->property->name }}  /</span>
            <h1 class="text-3xl font-bold text-gray-900">{{ $unit->name }}</h1>
        </div>
    </div>

    <!-- Placeholder Content -->
    <div class="bg-white rounded-lg shadow-sm p-8 text-center">
       <div class="text-left">
            <p>{{ $unit->description }}</p>
            <p class="text-gray-500 border-t border-gray-200 pt-2 mt-4">
                @if(!empty($unit->property->settings['url']))
                Go to property website: <a class="text-blue-600 hover:underline" href="{{ $unit->property->settings['url'] }}" target="_blank">{{ $unit->property->name }}</a>
                @else
                {{ $unit->property->name }}
                @endif
            </p>
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
