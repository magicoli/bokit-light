@extends('layouts.app')

@section('title', __('app.properties'))

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">{{ __('app.properties') }}</h1>
        <p class="text-gray-600 mt-2">Manage your rental properties and units</p>
    </div>
    
    @if($properties->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-8 text-center">
            <p class="text-gray-500">No properties configured yet</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($properties as $property)
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        {{ $property->name }}
                    </h2>
                    
                    @if($property->units->isEmpty())
                        <p class="text-sm text-gray-500">No units in this property</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($property->units as $unit)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <h3 class="font-medium text-gray-900">{{ $unit->name }}</h3>
                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ $unit->icalSources->count() }} calendar source(s)
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
