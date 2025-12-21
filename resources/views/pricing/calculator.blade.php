@extends('layouts.app')

@section('title', __('Pricing Calculator'))

@section('content')
<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">{{ __('Pricing Calculator') }}</h1>
        <a href="{{ route('pricing.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
            {{ __('Back to Rates') }}
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('calculation'))
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
            <h3 class="font-bold">{{ __('Calculation Result') }}</h3>
            <p><strong>{{ __('Total Price') }}:</strong> €{{ number_format(session('calculation')->total_amount, 2) }}</p>
            <p><strong>{{ __('Base Amount') }}:</strong> €{{ number_format(session('calculation')->base_amount, 2) }}</p>
            <p><strong>{{ __('Rate Used') }}:</strong> {{ session('calculation')->calculation_snapshot['rate_name'] }}</p>
            <p><strong>{{ __('Formula') }}:</strong> {{ session('calculation')->calculation_snapshot['formula'] }}</p>
            
            @if(session('test_booking'))
                <div class="mt-3">
                    <h4 class="font-semibold">{{ __('Test Booking Details') }}</h4>
                    <p><strong>{{ __('Property') }}:</strong> {{ session('test_booking')->property->name }}</p>
                    <p><strong>{{ __('Unit') }}:</strong> {{ session('test_booking')->unit->name }}</p>
                    <p><strong>{{ __('Check-in') }}:</strong> {{ session('test_booking')->check_in->format('Y-m-d') }}</p>
                    <p><strong>{{ __('Check-out') }}:</strong> {{ session('test_booking')->check_out->format('Y-m-d') }}</p>
                    <p><strong>{{ __('Nights') }}:</strong> {{ session('test_booking')->nights() }}</p>
                    <p><strong>{{ __('Adults') }}:</strong> {{ session('test_booking')->adults }}</p>
                    <p><strong>{{ __('Children') }}:</strong> {{ session('test_booking')->children }}</p>
                </div>
            @endif
        </div>
    @endif

    @if($errors->calculation)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ $errors->calculation }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">{{ __('Test Booking') }}</h2>
        
        <form action="{{ route('pricing.calculate') }}" method="POST" class="space-y-4">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Property') }}</label>
                    <select name="property_id" required class="w-full border rounded px-3 py-2">
                        <option value="">{{ __('Select Property') }}</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Unit') }}</label>
                    <select name="unit_id" required class="w-full border rounded px-3 py-2">
                        <option value="">{{ __('Select Unit') }}</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->property->name }} - {{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Check-in Date') }}</label>
                    <input type="date" name="check_in" required class="w-full border rounded px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Check-out Date') }}</label>
                    <input type="date" name="check_out" required class="w-full border rounded px-3 py-2">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Adults') }}</label>
                    <input type="number" name="adults" min="1" value="2" required class="w-full border rounded px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Children') }}</label>
                    <input type="number" name="children" min="0" value="0" class="w-full border rounded px-3 py-2">
                </div>
            </div>

            <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                {{ __('Calculate Price') }}
            </button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
        <h2 class="text-xl font-semibold mb-4">{{ __('Available Rates') }}</h2>
        
        @php
            $allRates = \App\Models\Rate::with(['unit', 'property'])->get();
        @endphp
        
        @if($allRates->count() === 0)
            <p class="text-gray-500">{{ __('No rates configured yet') }}</p>
        @else
            <div class="space-y-2">
                @foreach($allRates as $rate)
                    <div class="border rounded p-3 {{ $rate->is_active ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-semibold">{{ $rate->name }}</h4>
                                <p class="text-sm text-gray-600">
                                    @if($rate->unit_id)
                                        Unit: {{ $rate->unit->property->name }} - {{ $rate->unit->name }}
                                    @elseif($rate->unit_type)
                                        Type: {{ $rate->unit_type }}
                                    @else
                                        Property: {{ $rate->property->name }}
                                    @endif
                                </p>
                                <p class="text-sm"><strong>Base:</strong> €{{ number_format($rate->base_amount, 2) }}</p>
                                <p class="text-sm"><strong>Formula:</strong> <code class="bg-gray-100 px-1 py-0.5 text-xs rounded">{{ $rate->calculation_formula }}</code></p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm text-gray-500">Priority: {{ $rate->priority }}</span><br>
                                @if($rate->is_active)
                                    <span class="text-green-600 text-sm">Active</span>
                                @else
                                    <span class="text-red-600 text-sm">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection