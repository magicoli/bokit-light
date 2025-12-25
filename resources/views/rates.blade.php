@extends('layouts.app')

@section('title', __('rates.title'))

@section('styles')
@vite('resources/css/forms.css')
@vite('resources/css/rates.css')
@endsection

@section('scripts')
@vite('resources/js/rates.js')
<script>
// Pass data to JavaScript
window.ratesFormData = {
    units: @json($units),
    coupons: @json($coupons),
    unitTypes: @json($allUnitTypes)
};
</script>
@endsection

@section('content')
<div class="main-content">
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
