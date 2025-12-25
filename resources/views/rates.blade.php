@extends('layouts.app')

@section('title', __('rates.title'))

@section('styles')
@vite('resources/css/forms.css')
@vite('resources/css/rates.css')
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
            $priorityOptions = [
                'high' => __('rates.priority_high'),
                'normal' => __('rates.priority_normal'),
                'low' => __('rates.priority_low'),
            ];
        @endphp
        {!! \App\Models\Rate::form(route('rates.store'))
            ->fieldOptions('property_id', $propertyOptions)
            ->fieldOptions('priority', $priorityOptions)
            ->render() !!}
    </div>

</div>
@endsection
