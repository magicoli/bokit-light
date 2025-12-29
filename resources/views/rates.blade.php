{{-- NEVER INCLUDE DIRECT JAVASCRIPT IN TEMPLATES, ALWAYS USE .js FILES --}}
{{-- NEVER INCLUDE DIRECT CSS IN TEMPLATES, ALWAYS USE .css FILES --}}

@extends('layouts.app')

@section('title', __('rates.title'))

@section('styles')
@vite('resources/css/forms.css')
@vite('resources/css/rates.css')
@vite('resources/css/flatpickr.css')
@endsection

@section('scripts')
@vite('resources/js/flatpickr.js')
@vite('resources/js/forms.js')
@vite('resources/js/rates.js')

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

@section('sidebar-right')
<!-- Dumb test section -->
<div class="card">
    <div class="card-header">
        <h3>{{ __('rates.test_sidebar') }}</h3>
    </div>
    <div class="card-body">
        <p>This is a test section for the sidebar.</p>
    </div>
</div>
@endsection

@section('content')
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
        {!! \App\Models\Rate::form('formAdd', route('rates.store'))
            ->fieldOptions('property_id', $propertyOptions)
            ->fieldOptions('priority', $priorityOptions)
            ->render() !!}
    </div>
@endsection
