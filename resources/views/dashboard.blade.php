@extends('layouts.app')

@section('title', 'Calendar - Bokit')

@section('content')
<div x-data="calendar()" x-cloak>
    <!-- Month Navigation -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center justify-between">
            <a href="?month={{ $prevMonth->format('Y-m-d') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                ← Previous
            </a>
            
            <h2 class="text-2xl font-bold text-gray-900">
                {{ $currentMonth->format('F Y') }}
            </h2>
            
            <a href="?month={{ $nextMonth->format('Y-m-d') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Next →
            </a>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <!-- Header with day names -->
        <div class="grid grid-cols-8 border-b border-gray-200 bg-gray-50">
            <div class="px-3 py-2 text-sm font-medium text-gray-700 border-r border-gray-200">
                Property
            </div>
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
            <div class="px-1 py-2 text-center text-xs font-medium text-gray-700 
                        {{ $loop->last ? '' : 'border-r border-gray-200' }}">
                {{ $day }}
            </div>
            @endforeach
        </div>

        <!-- Property rows -->
        @foreach($properties as $property)
        <div class="grid grid-cols-8 border-b border-gray-200 hover:bg-gray-50 transition-colors">
            <!-- Property name -->
            <div class="px-3 py-4 border-r border-gray-200 flex items-center">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: {{ $property->color }}"></div>
                    <span class="font-medium text-gray-900 text-sm">{{ $property->name }}</span>
                </div>
            </div>

            <!-- Calendar days (simplified - showing weeks) -->
            @php
                $weeks = collect($calendarDays)->chunk(7);
            @endphp
            
            @foreach($weeks as $weekIndex => $week)
                @foreach($week as $day)
                <div class="relative min-h-[60px] p-1 {{ $day['isCurrentMonth'] ? '' : 'bg-gray-50' }} 
                            {{ $day['isToday'] ? 'bg-blue-50' : '' }}
                            {{ $loop->parent->last && $loop->last ? '' : 'border-r' }} border-gray-100">
                    
                    <!-- Day number (only show in first row) -->
                    @if($property === $properties->first())
                    <div class="text-xs text-gray-500 mb-1">
                        {{ $day['date']->format('j') }}
                    </div>
                    @endif

                    <!-- Bookings for this day -->
                    @foreach($property->bookings as $booking)
                        @if($booking->check_in->lte($day['date']) && $booking->check_out->gt($day['date']))
                        <div @click="showBooking({{ $booking->id }})"
                             class="absolute inset-x-1 top-1 bottom-1 rounded cursor-pointer 
                                    text-white text-xs p-1 overflow-hidden hover:shadow-lg transition-shadow"
                             style="background-color: {{ $property->color }}; opacity: 0.9"
                             title="{{ $booking->guest_name }}">
                            @if($booking->check_in->isSameDay($day['date']))
                                <div class="font-semibold truncate">{{ $booking->guest_name }}</div>
                            @endif
                        </div>
                        @endif
                    @endforeach
                </div>
                @endforeach
            @endforeach
        </div>
        @endforeach
    </div>

    <!-- Booking Detail Modal -->
    <div x-show="selectedBooking" 
         x-cloak
         @click.self="selectedBooking = null"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.stop>
            <template x-if="selectedBooking">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-xl font-bold text-gray-900" x-text="selectedBooking.guest_name"></h3>
                        <button @click="selectedBooking = null" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm font-medium text-gray-500">Property:</span>
                            <span class="text-sm text-gray-900 ml-2" x-text="selectedBooking.property.name"></span>
                        </div>
                        
                        <div>
                            <span class="text-sm font-medium text-gray-500">Check-in:</span>
                            <span class="text-sm text-gray-900 ml-2" x-text="formatDate(selectedBooking.check_in)"></span>
                        </div>
                        
                        <div>
                            <span class="text-sm font-medium text-gray-500">Check-out:</span>
                            <span class="text-sm text-gray-900 ml-2" x-text="formatDate(selectedBooking.check_out)"></span>
                        </div>
                        
                        <div>
                            <span class="text-sm font-medium text-gray-500">Source:</span>
                            <span class="text-sm text-gray-900 ml-2" x-text="selectedBooking.source_name"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function calendar() {
    return {
        selectedBooking: null,
        
        async showBooking(bookingId) {
            try {
                const response = await fetch(`/booking/${bookingId}`);
                this.selectedBooking = await response.json();
            } catch (error) {
                console.error('Failed to load booking:', error);
            }
        },
        
        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    }
}
</script>
@endsection
