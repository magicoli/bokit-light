@extends('layouts.app')

@section('title', __('Pricing Management'))

@section('content')
<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">{{ __('Pricing Management') }}</h1>
        <a href="{{ route('pricing.calculator') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            {{ __('Test Calculator') }}
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <!-- Add Rate Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">{{ __('Add New Rate') }}</h2>
        
        <form action="{{ route('pricing.store') }}" method="POST" class="space-y-4">
            @csrf
            
            <!-- First Row: Property, Unit Type, Unit, Coupon -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Property') }}*</label>
                    <select name="property_id" id="property_select" required class="w-full border rounded px-3 py-2">
                        <option value="">{{ __('Select Property') }}</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Unit Type') }}</label>
                    <input type="text" name="unit_type" id="unit_type_input" list="unit_types" 
                           class="w-full border rounded px-3 py-2" placeholder="{{ __('Type or add new') }}">
                    <datalist id="unit_types">
                        <!-- Will be populated dynamically -->
                    </datalist>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Unit') }}</label>
                    <select name="unit_id" id="unit_select" class="w-full border rounded px-3 py-2">
                        <option value="">{{ __('Select Unit') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Coupon') }}</label>
                    <input type="text" name="coupon" id="coupon_input" list="coupons" 
                           class="w-full border rounded px-3 py-2" placeholder="{{ __('Type or add new') }}">
                    <datalist id="coupons">
                        <!-- Will be populated dynamically -->
                    </datalist>
                </div>
            </div>

            <!-- Second Row: Base Rate, Reference Rate, Calculation Formula -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Base Rate') }}</label>
                    <input type="number" step="0.01" name="base_rate" required class="w-full border rounded px-3 py-2">
                    <small class="text-gray-600">{{ __('Variable: rate') }}</small>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Reference Rate') }}</label>
                    <select name="reference_rate_id" id="reference_rate_select" class="w-full border rounded px-3 py-2">
                        <option value="">{{ __('Select reference rate') }}</option>
                    </select>
                    <small class="text-gray-600">{{ __('Variable: ref_rate') }}</small>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Calculation Formula') }}</label>
                    <input type="text" name="calculation_formula" value="booking_nights * rate" required 
                           class="w-full border rounded px-3 py-2">
                    <small class="text-gray-600">
                        Variables: rate, ref_rate, booking_nights, guests, adults, children
                    </small>
                </div>
            </div>

            <!-- Third Row: Active, Booking Dates, Stay Dates, Priority -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" checked class="mr-2">
                    <label for="is_active" class="text-sm font-medium">{{ __('Active') }}</label>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Booking From') }}</label>
                    <input type="date" name="booking_from" class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Booking To') }}</label>
                    <input type="date" name="booking_to" class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Stay From') }}</label>
                    <input type="date" name="stay_from" class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Stay To') }}</label>
                    <input type="date" name="stay_to" class="w-full border rounded px-3 py-2">
                </div>
            </div>

            <!-- Fourth Row: Priority and Name -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Priority') }}</label>
                    <select name="priority" class="w-full border rounded px-3 py-2">
                        <option value="high">{{ __('High') }}</option>
                        <option value="normal" selected>{{ __('Normal') }}</option>
                        <option value="low">{{ __('Low') }}</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">{{ __('Name this rate') }}</label>
                    <input type="text" name="name" class="w-full border rounded px-3 py-2" 
                           placeholder="{{ __('Optional, auto-generated if empty') }}">
                </div>
            </div>

            <!-- Placeholder for Conditions -->
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Conditions') }}</label>
                <textarea name="conditions" rows="3" class="w-full border rounded px-3 py-2" 
                          placeholder="{{ __('Future feature: rate conditions') }}" disabled></textarea>
            </div>

            @error('scope')
                <div class="text-red-600 text-sm">{{ $message }}</div>
            @enderror

            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                {{ __('Add Rate') }}
            </button>
        </form>
    </div>

    <!-- Rates List -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">{{ __('Current Rates') }}</h2>
        
        @if($rates->count() === 0)
            <p class="text-gray-500">{{ __('No rates configured yet') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left p-2">{{ __('Display Name') }}</th>
                            <th class="text-left p-2">{{ __('Scope') }}</th>
                            <th class="text-left p-2">{{ __('Base Rate') }}</th>
                            <th class="text-left p-2">{{ __('Reference') }}</th>
                            <th class="text-left p-2">{{ __('Formula') }}</th>
                            <th class="text-left p-2">{{ __('Priority') }}</th>
                            <th class="text-left p-2">{{ __('Active') }}</th>
                            <th class="text-left p-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rates as $rate)
                            <tr class="border-b">
                                <td class="p-2">
                                    <strong>{{ $rate->display_name }}</strong>
                                    <br><small class="text-gray-500">ID: {{ $rate->id }}</small>
                                </td>
                                <td class="p-2">
                                    @if($rate->unit_id)
                                        {{ $rate->unit->property->name }} - {{ $rate->unit->name }}
                                    @elseif($rate->unit_type)
                                        Type: {{ $rate->unit_type }}
                                    @else
                                        {{ $rate->rateProperty->name }}
                                    @endif
                                </td>
                                <td class="p-2">€{{ number_format($rate->base_rate, 2) }}</td>
                                <td class="p-2">
                                    @if($rate->referenceRate)
                                        €{{ number_format($rate->referenceRate->base_rate, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="p-2">
                                    <code class="bg-gray-100 px-1 py-0.5 text-xs rounded">{{ $rate->calculation_formula }}</code>
                                </td>
                                <td class="p-2">
                                    @if($rate->priority === 'high')
                                        <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800">high</span>
                                    @elseif($rate->priority === 'low')
                                        <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">low</span>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">normal</span>
                                    @endif
                                </td>
                                <td class="p-2">
                                    @if($rate->is_active)
                                        <span class="text-green-600">✓</span>
                                    @else
                                        <span class="text-red-600">✗</span>
                                    @endif
                                </td>
                                <td class="p-2">
                                    <form action="{{ route('pricing.destroy', $rate) }}" method="POST" onsubmit="return confirm('Delete this rate?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const propertySelect = document.getElementById('property_select');
    const unitSelect = document.getElementById('unit_select');
    const unitTypeInput = document.getElementById('unit_type_input');
    const unitTypesDatalist = document.getElementById('unit_types');
    const couponInput = document.getElementById('coupon_input');
    const couponsDatalist = document.getElementById('coupons');
    const referenceRateSelect = document.getElementById('reference_rate_select');
    
    // Store original data
    let allUnits = @json($units);
    let allCoupons = @json($coupons ?? []);
    
    // Update when property changes
    propertySelect.addEventListener('change', function() {
        const propertyId = this.value;
        
        // Update units
        unitSelect.innerHTML = '<option value="">{{ __("Select Unit") }}</option>';
        const filteredUnits = allUnits.filter(unit => unit.property_id == propertyId);
        filteredUnits.forEach(unit => {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = `${unit.property.name} - ${unit.name}`;
            unitSelect.appendChild(option);
        });
        
        // Update unit types
        updateUnitTypes(propertyId);
        
        // Update coupons
        updateCoupons(propertyId);
        
        // Update reference rates
        updateReferenceRates(propertyId);
        
        // Clear other fields
        couponInput.value = '';
        referenceRateSelect.value = '';
    });
    
    function updateUnitTypes(propertyId) {
        const unitTypes = [...new Set(allUnits
            .filter(unit => unit.property_id == propertyId && unit.unit_type)
            .map(unit => unit.unit_type)
        )];
        
        unitTypesDatalist.innerHTML = '';
        unitTypes.forEach(type => {
            const option = document.createElement('option');
            option.value = type;
            unitTypesDatalist.appendChild(option);
        });
    }
    
    function updateCoupons(propertyId) {
        const propertyCoupons = allCoupons.filter(coupon => coupon.property_id == propertyId && coupon.is_active);
        
        couponsDatalist.innerHTML = '';
        propertyCoupons.forEach(coupon => {
            const option = document.createElement('option');
            option.value = coupon.code;
            option.textContent = `${coupon.code} - ${coupon.name}`;
            couponsDatalist.appendChild(option);
        });
    }
    
    function updateReferenceRates(propertyId) {
        fetch(`/api/reference-rates/${propertyId}`)
            .then(response => response.json())
            .then(rates => {
                referenceRateSelect.innerHTML = '<option value="">{{ __("Select reference rate") }}</option>';
                rates.forEach(rate => {
                    const option = document.createElement('option');
                    option.value = rate.id;
                    option.textContent = `${rate.display_name} - €${rate.base_rate}`;
                    referenceRateSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error loading reference rates:', error));
    }
    
    // Clear mutually exclusive fields
    unitSelect.addEventListener('change', function() {
        if (this.value) {
            unitTypeInput.value = '';
        }
    });
    
    unitTypeInput.addEventListener('input', function() {
        if (this.value) {
            unitSelect.value = '';
        }
    });
});
</script>
@endsection