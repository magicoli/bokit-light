{{-- NEVER INCLUDE DIRECT JAVASCRIPT IN TEMPLATES, ALWAYS USE .js FILES --}}
{{-- NEVER INCLUDE DIRECT CSS IN TEMPLATES, ALWAYS USE .css FILES --}}

@extends('layouts.app')

@section('title', __('rate.label'))

@push('styles')
@vite('resources/css/rates.css')
@endpush

@push('scripts')
@vite('resources/js/rates.js')

<script>
// TODO: this should be replaced by proper methods to follow the no javascript in templates rule
window.ratesFormData = {
    units: @json($units),
    coupons: @json($coupons),
    unitTypes: @json($allUnitTypes)
};
</script>
@endpush

@section('sidebar-left')
<!-- Rate Calculator Widget -->
<div class="card rate-calculator">
    <div class="card-header">
        <h3>{{ __('rate.test_calculator') }}</h3>
    </div>
    <div class="card-body">
        @include('components.rate-calculator')
    </div>
</div>

<!-- Add Rate Form -->
<div class="card">
    <h2 class="card-header">{{ __('rate.add_rate') }}</h2>
    @php
        $propertyOptions = $properties->pluck('name', 'id')->toArray();
    @endphp
    {!! \App\Models\Rate::form('formAdd', route('rates.store'))
        ->fieldOptions('property_id', $propertyOptions)
        ->fieldOptions('priority', $priorityOptions)
        ->render() !!}
</div>
@endsection

@section('sidebar-right')
@endsection

@section('content')
    <!-- Rates List -->
    <div class="card">
        {!! \App\Models\Rate::list($rates, 'rates')->render() !!}
    </div>
@endsection
