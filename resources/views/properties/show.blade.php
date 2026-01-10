@extends('layouts.app')

@section('title', $property->name)

@push('styles')
@vite('resources/css/properties.css')
@endpush

@section('content')
<div class="property-show-container">
    <!-- Header -->
    <div class="property-show-header">
        <div class="header-row">
            <h1 class="title">{{ $property->name }}</h1>
            @if(user_can('manage', $property))
            <div class="actions">
                <a href="{{ route('calendar', ['property' => $property->slug]) }}"
                   class="action-link">
                    {{ __('app.view_calendar') }}
                </a>
                <span class="separator">|</span>
                <a href="#" class="action-link">
                    {{ __('app.edit_property') }}
                </a>
            </div>
            @endif
        </div>

        @if(!empty($property->settings['url']))
        <p class="website">
            {{ __('app.website') }}: <a href="{{ $property->settings['url'] }}" target="_blank">{{ $property->name }}</a>
        </p>
        @endif
    </div>

    <!-- Units List -->
    @if($property->units->isEmpty())
        <div class="empty-state">
            <p class="message">{{ __('app.no_units_in_property') }}</p>
        </div>
    @else
        <div class="units-grid-large">
            @foreach($property->units as $unit)
                <div class="unit-card-large">
                    <div class="unit-header">
                        <a href="{{ route('units.show', [$property, $unit]) }}"
                           class="unit-name">
                            {{ $unit->name }}
                        </a>
                        @if(user_can('manage', $property))
                        <div class="unit-actions">
                            <a href="{{ route('units.show', [$property, $unit]) }}"
                               class="action-link">
                                {{ __('app.view') }}
                            </a>
                            <span class="separator">|</span>
                            <a href="{{ route('units.edit', [$property, $unit]) }}"
                               class="action-link">
                                {{ __('app.edit') }}
                            </a>
                        </div>
                        @endif
                    </div>

                    @if($unit->description)
                    <p class="unit-description">
                        {{ $unit->description }}
                    </p>
                    @endif

                    @if(user_can('manage', $property))
                    <p class="unit-footer">
                        {{ $unit->icalSources->count() }} {{ __('app.calendar_sources') }}
                    </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
