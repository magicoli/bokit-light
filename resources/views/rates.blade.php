{{-- NEVER INCLUDE DIRECT JAVASCRIPT IN TEMPLATES, ALWAYS USE .js FILES --}}
{{-- NEVER INCLUDE DIRECT CSS IN TEMPLATES, ALWAYS USE .css FILES --}}

@extends('layouts.app')

@section('title', __('rates.title'))

@section('styles')
@vite('resources/css/forms.css')
@vite('resources/css/rates.css')
@endsection

@section('scripts')
@vite('resources/js/forms.js')
@vite('resources/js/rates.js')
@vite('resources/js/rate-calculator.js')

<script>
// TODO: this should be replaced by proper methods to follow the no javascript in templates rule
window.ratesFormData = {
    units: @json($units),
    coupons: @json($coupons),
    unitTypes: @json($allUnitTypes)
};
</script>
@endsection

@section('sidebar-left')
@endsection

@section('content')
<div class="main-content">
    <!-- Rate Calculator Widget -->
    <div class="card rate-calculator">
        <div class="card-header">
            <h3>{{ __('rates.test_calculator') }}</h3>
        </div>
        <div class="card-body">
            @include('components.rate-calculator')
        </div>
    </div>

    <!-- Rates List -->
    <div class="card">
        {!! \App\Models\Rate::list($rates, 'rates')->render() !!}
    </div>

    <!-- Add Rate Form -->
    <div class="card">
        <h2 class="card-header">{{ __('rates.add_rate') }}</h2>
        @php
            $propertyOptions = $properties->pluck('name', 'id')->toArray();
        @endphp
        {!! \App\Models\Rate::form(route('rates.store'))
            ->fieldOptions('property_id', $propertyOptions)
            ->fieldOptions('priority', $priorityOptions)
            ->render() !!}
    </div>

</div>
@endsection
