@extends('layouts.app')

@section('title', $property->name)

@section('content')
<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-baseline justify-between">
            <h1 class="text-3xl font-bold text-gray-900">{{ $property->name }}</h1>
            @if(auth()->check() && (auth()->user()->isAdmin() || $property->users()->where('users.id', auth()->id())->exists()))
            <div class="flex gap-3 text-sm">
                <a href="{{ route('calendar', ['property' => $property->slug]) }}"
                   class="text-blue-600 hover:text-blue-800">
                    {{ __('app.view_calendar') }}
                </a>
                <span class="text-gray-300">|</span>
                <a href="#" class="text-blue-600 hover:text-blue-800">
                    {{ __('app.edit_property') }}
                </a>
            </div>
            @endif
        </div>

        @if(!empty($property->settings['url']))
        <p class="text-gray-600 mt-2">
            {{ __('app.website') }}: <a class="text-blue-600 hover:underline" href="{{ $property->settings['url'] }}" target="_blank">{{ $property->name }}</a>
        </p>
        @endif
    </div>

    <!-- Units List -->
    @if($property->units->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-8 text-center">
            <p class="text-gray-500">{{ __('app.no_units_in_property') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($property->units as $unit)
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-blue-400 hover:shadow-md transition-all">
                    <div class="flex items-baseline justify-between gap-3 mb-3">
                        <a href="{{ route('units.show', [$property, $unit]) }}"
                           class="text-xl font-semibold text-gray-900 hover:text-blue-600">
                            {{ $unit->name }}
                        </a>
                        @if(auth()->check() && (auth()->user()->isAdmin() || $property->users()->where('users.id', auth()->id())->exists()))
                        <div class="flex gap-2 text-xs">
                            <a href="{{ route('units.show', [$property, $unit]) }}"
                               class="text-blue-600 hover:text-blue-800">
                                {{ __('app.view') }}
                            </a>
                            <span class="text-gray-300">|</span>
                            <a href="{{ route('units.edit', [$property, $unit]) }}"
                               class="text-blue-600 hover:text-blue-800">
                                {{ __('app.edit') }}
                            </a>
                        </div>
                        @endif
                    </div>

                    @if($unit->description)
                    <p class="text-sm text-gray-600 mb-3">
                        {{ $unit->description }}
                    </p>
                    @endif

                    @if(auth()->check() && (auth()->user()->isAdmin() || $property->users()->where('users.id', auth()->id())->exists()))
                    <p class="text-xs text-gray-500 border-t border-gray-200 pt-2">
                        {{ $unit->icalSources->count() }} {{ __('app.calendar_sources') }}
                    </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
