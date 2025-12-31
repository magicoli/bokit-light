@extends('layouts.app')

@section('title', __('app.properties'))
@section('subtitle', __('app.manage_properties_subtitle'))

@section('styles')
@vite('resources/css/forms.css')
@vite('resources/css/properties.css')
@vite('resources/css/flatpickr.css')
@endsection

@section('sidebar-left')
<!-- Rate Calculator Widget -->
<div class="card rate-calculator">
    <div class="card-header">
        <h3>{{ __('rates.test_calculator') }}</h3>
    </div>
    <div class="card-body">
        @include('components.rate-calculator')
    </div>
</div>
@endsection

@section('content')
<div class="properties-container">
    @if($properties->isEmpty())
        <div class="empty-state">
            <p class="message">{{ __('app.no_properties_yet') }}</p>
        </div>
    @else
        <div class="properties-list">
            @foreach($properties as $property)
                <div class="property-card">
                    <h2 class="property-name">
                        {{ $property->name }}
                    </h2>

                    @if($property->units->isEmpty())
                        <p class="no-units">{{ __('app.no_units_yet') }}</p>
                    @else
                        <div class="units-grid">
                            @foreach($property->units as $unit)
                                <div class="unit-card">
                                    <div class="unit-header">
                                        <a href="{{ route('units.show', [$property, $unit]) }}"
                                           class="unit-name">
                                            {{ $unit->name }}
                                        </a>
                                        @if(user_can('manage', $unit->property))
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
                                    <p class="unit-meta">
                                        {{ $unit->icalSources->count() }} {{ __('app.calendar_sources') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@section('scripts')
@vite('resources/js/flatpickr.js')
@vite('resources/js/forms.js')
@endsection
