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
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Name') }}</label>
                    <input type="text" name="name" required class="w-full border rounded px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Base Amount') }}</label>
                    <input type="number" step="0.01" name="base_amount" required class="w-full border rounded px-3 py-2">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Calculation Formula') }}</label>
                <input type="text" name="calculation_formula" value="booking_nights * rate" required 
                       class="w-full border rounded px-3 py-2">
                <small class="text-gray-600">
                    Variables: rate, booking_nights, guests, adults, children
                </small>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Property') }}</label>
                    <select name="property_id" id="property_select" class="w-full border rounded px-3 py-2">
                        <option value="">{{ __('Select Property') }}</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Unit Type') }}</label>
                    <input type="text" name="unit_type" list="unit_types" class="w-full border rounded px-3 py-2">
                    <datalist id="unit_types">
                        @foreach($unitTypes as $type)
                            <option value="{{ $type }}">
                        @endforeach
                    </datalist>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Specific Unit') }}</label>
                    <select name="unit_id" id="unit_select" class="w-full border rounded px-3 py-2">
                        <option value="">{{ __('Select Unit') }}</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->property->name }} - {{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Priority') }}</label>
                    <input type="number" name="priority" value="0" min="0" class="w-full border rounded px-3 py-2">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" checked class="mr-2">
                    <label for="is_active" class="text-sm font-medium">{{ __('Active') }}</label>
                </div>
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
                            <th class="text-left p-2">{{ __('Name') }}</th>
                            <th class="text-left p-2">{{ __('Scope') }}</th>
                            <th class="text-left p-2">{{ __('Base Amount') }}</th>
                            <th class="text-left p-2">{{ __('Formula') }}</th>
                            <th class="text-left p-2">{{ __('Priority') }}</th>
                            <th class="text-left p-2">{{ __('Active') }}</th>
                            <th class="text-left p-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rates as $rate)
                            <tr class="border-b">
                                <td class="p-2">{{ $rate->name }}</td>
                                <td class="p-2">
                                    @if($rate->unit_id)
                                        {{ $rate->unit->property->name }} - {{ $rate->unit->name }}
                                    @elseif($rate->unit_type)
                                        Type: {{ $rate->unit_type }}
                                    @else
                                        {{ $rate->property->name }}
                                    @endif
                                </td>
                                <td class="p-2">€{{ number_format($rate->base_amount, 2) }}</td>
                                <td class="p-2">
                                    <code class="bg-gray-100 px-1 py-0.5 text-xs rounded">{{ $rate->calculation_formula }}</code>
                                </td>
                                <td class="p-2">{{ $rate->priority }}</td>
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
    const unitTypeInput = document.querySelector('input[name="unit_type"]');
    
    // Clear other fields when one is selected
    propertySelect.addEventListener('change', function() {
        if (this.value) {
            unitSelect.value = '';
            unitTypeInput.value = '';
        }
    });
    
    unitSelect.addEventListener('change', function() {
        if (this.value) {
            propertySelect.value = '';
            unitTypeInput.value = '';
        }
    });
    
    unitTypeInput.addEventListener('input', function() {
        if (this.value) {
            propertySelect.value = '';
            unitSelect.value = '';
        }
    });
});
</script>
@endsection