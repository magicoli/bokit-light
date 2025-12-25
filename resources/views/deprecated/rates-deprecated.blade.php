@extends('layouts.app')

@section('title', __('rates.title'))

@section('styles')
@vite('resources/css/forms.css')
@vite('resources/css/rates.css')
@endsection

@section('scripts')
@vite('resources/js/forms.js')
@vite('resources/js/rates.js')
@endsection

@php
use App\Forms\Form;
@endphp

@section('content')
<div class="main-content">

    <!-- Rates List -->
    <div class="card rates-list">
        @if($rates->count() === 0)
            <p>{{ __('rates.no_rates_configured_yet') }}</p>
        @else
            @foreach($rates as $rate)
                <div class="rate-card {{ $rate->is_active ? 'active' : 'inactive' }}">
                    <div class="rate-header">
                        <div class="rate-name">{{ $rate->display_name }}</div>
                        <div class="rate-meta">
                            <span class="priority-badge {{ $rate->priority }}">
                                {{ __('rates.priority_' . $rate->priority) }}
                            </span>
                            <span class="status-indicator {{ $rate->is_active ? 'enabled' : 'disabled' }}">
                                {{ $rate->is_active ? '✓' : '✗' }}
                            </span>
                        </div>
                    </div>

                    <div class="rate-details">
                        <div class="rate-scope">
                            @if($rate->unit_id)
                                {{ $rate->unit->property->name }} - {{ $rate->unit->name }}
                            @elseif($rate->unit_type)
                                {{ __('forms.unit_type') }}: {{ $rate->unit_type }}
                            @else
                                {{ $rate->rateProperty->name }}
                            @endif
                        </div>

                        <div class="rate-amount">
                            {{ __('rates.base_rate') }}: €{{ number_format($rate->base_rate, 2) }}
                        </div>

                        @if($rate->referenceRate)
                            <div class="rate-amount">
                                {{ __('rates.reference_rate') }}: €{{ number_format($rate->referenceRate->base_rate, 2) }}
                            </div>
                        @endif

                        <div class="formula-code">
                            {{ __('rates.calculation_formula') }}:
                            <code>{{ $rate->calculation_formula }}</code>
                        </div>
                    </div>

                    <div class="rate-actions">
                        <form action="{{ route('rates.destroy', $rate) }}" method="POST" onsubmit="return confirm('Delete this rate?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <!-- Add Rate Form -->
    <div class="card form-container">
        <h2 class="card-header">{{ __('rates.add_rate') }}</h2>
        <form action="{{ route('rates.store') }}" method="POST" x-data="{ hasProperty: false }">
            @csrf

            @error('scope')
                <div>{{ $message }}</div>
                <div class="text-red-600 text-sm">{{ $message }}</div>
            @enderror

            <!-- First Row: Property, Unit Type, Unit, Coupon -->
            <div class="fields-row">
                <fieldset class="field">
                    <label>{{ __('app.property') }}*</label>
                    <select name="property_id" x-model="hasProperty" id="property_select" class="input" required
                        data-units="{{ $units->toJson() }}"
                        data-coupons="{{ $coupons->toJson() }}"
                    >
                        <option value="">{{ __('forms.select_property') }}</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ old('property_id') == $property->id ? 'selected' : '' }}>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </fieldset>

                <fieldset x-show="hasProperty" class="field">
                    <label>{{ __('forms.unit_type') }}</label>
                        <select name="unit_type" id="unit_type_select" class="input"
                            data-placeholder="{{ __('forms.any_unit_type') }}"
                        >
                            <option value="">{{ __('forms.any_unit_type') }}</option>
                            <!-- Will be populated dynamically -->
                        </select>
                        <button type="button" class="add-button" onclick="addNewUnitType()">+</button>
                </fieldset>

                <fieldset x-show="hasProperty" class="field">
                    <label>{{ __('forms.unit') }}</label>
                    <select name="unit_id" id="unit_select" class="input"
                        data-placeholder="{{ __('forms.any_unit') }}"
                    >
                        <option value="">{{ __('forms.any_unit') }}</option>
                    </select>
                </fieldset>

                <fieldset x-show="hasProperty" class="field">
                    <label>{{ __('forms.coupon') }}</label>
                    <div class="input-group">
                        <select name="coupon_code" id="coupon_select" class="input"
                            data-placeholder="{{ __('forms.any_coupon') }}"
                        >
                            <option value="">{{ __('forms.any_coupon') }}</option>
                            <!-- Will be populated dynamically -->
                        </select>
                        <button type="button" class="add-button" onclick="addNewCoupon()">+</button>
                    </div>
                </fieldset>
            </div>

            <div id="row-base-rate" x-show="hasProperty" class="fields-row hidden">
                <fieldset class="field">
                    <label>{{ __('rates.base_rate') }}</label>
                    <input type="number" step="0.01" name="base_rate" class="w-[7rem]" required
                           value="{{ old('base_rate') }}">
                </fieldset>

                <fieldset class="field">
                    <label>{{ __('rates.reference_rate') }}</label>
                    <select name="reference_rate_id" id="reference_rate_select"
                        data-placeholder="{{ __('forms.no_reference_rate') }}"
                    >
                        <option value="">{{ __('forms.no_reference_rate') }}</option>
                    </select>
                </fieldset>

            </div>

            <div id="row-formula" x-show="hasProperty" class="fields-row" x-show="hasProperty">
                <fieldset class="field w-full">
                    <label>{{ __('rates.calculation_formula') }}</label>
                    <input name="calculation_formula" type="text" class="w-full" value="{{ old('calculation_formula', 'booking_nights * rate') }}" required
                        placeholder="booking_nights * rate">
                    <div class="description variables-hint">
                        {!! __('forms.allowed_variables', [
                            'variables' => '<code>' . implode('</code> <code>', [
                                'rate',
                                'ref_rate',
                                'booking_nights',
                                'guests',
                                'adults',
                                'children',
                            ]) . '</code>'
                        ]) !!}
                    </div>
                </fieldset>
            </div>

            <div id="row-allowed-dates" x-show="hasProperty" class="fields-row">
                <fieldset class="field">
                    <label>{{ __('rates.booking_date') }}</label>
                    <div class="input-group">
                        <input data-alpine-date name="booking_from" size="10" class="input" placeholder="{{ __('forms.date_from') }}" value="{{ old('booking_from') }}">
                        -
                        <input data-alpine-date type="text" name="booking_to" size="10" class="input" placeholder="{{ __('forms.date_to') }}" value="{{ old('booking_to') }}">
                    </div>
                </fieldset>

                <fieldset class="field">
                    <label>{{ __('rates.stay') }}</label>
                    <div class="input-group">
                        <input data-alpine-date name="stay_from" size="10" class="input" placeholder="{{ __('forms.date_from') }}" value="{{ old('stay_from') }}">
                        -
                        <input data-alpine-date name="stay_to" size="10" class="input" placeholder="{{ __('forms.date_to') }}" value="{{ old('stay_to') }}">
                    </div>
                </fieldset>

                <fieldset>
                    <label>Debug standard date</label>
                    <input name="debug_date" type="date" size="10" class="input" value="{{ old('debug_date') }}">
                </fieldset>

            </div>

            <div id="row-active-priority-name" x-show="hasProperty" class="fields-row">
                <fieldset class="field">
                    <label for="is_active">{{ __('forms.enabled') }}</label>
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active') ? 'checked' : 'checked' }}>
                </fieldset>
                <fieldset class="field">
                    <label>{{ __('rates.priority') }}</label>
                    <select name="priority">
                        {{ Form::selectOptions([
                        'high' => __('rates.priority_high'),
                        'normal' => __('rates.priority_normal'),
                        'low' => __('rates.priority_low')
                        ], "high") }}
                    </select>
                </fieldset>
                <div class="fields-row">
                    <fieldset class="field">
                        <label>{{ __('rates.name_this_rate') }}</label>
                        <input type="text" name="name"
                               placeholder="{{ __('rates.name_this_rate_placeholder') }}"
                               value="{{ old('name') }}">
                    </fieldset>
                </div>
            </div>

            <fieldset id="field-conditions" x-show="hasProperty" class="conditions-field">
                <label>{{ __('rates.conditions') }}</label>
                <small>
                    {{ __('rates.conditions_placeholder') }}
                </small>
            </fieldset>

            <div id="row-buttons" zzzx-show="hasProperty" class="fields-row">
                <button type="reset" class="reset-button">
                    {{ __('forms.reset') }}
                </button>
                <button type="submit" class="submit-button btn btn-blue ms-auto">
                    {{ __('forms.save') }}
                </button>
            </div>

        </form>
    </div>

</div>

@endsection
