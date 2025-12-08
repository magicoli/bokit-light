@extends('layouts.app')

@section('title', 'Calendar - Bokit')

@section('content')
<div x-data="calendar()" x-cloak>
    <!-- Navigation Bar -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <!-- Left: Year navigation -->
            <div class="flex items-center space-x-2">
                <a href="?date={{ $prevYear->format('Y-m-d') }}&view={{ $view }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    ⏪
                </a>
                <a href="?date={{ $prevPeriod->format('Y-m-d') }}&view={{ $view }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    ← {{ $view === 'week' ? 'Week' : 'Month' }}
                </a>
            </div>
            
            <!-- Center: Current period + Today button -->
            <div class="flex items-center space-x-4">
                <h2 class="text-2xl font-bold text-gray-900">
                    @if($view === 'week')
                        Week {{ $startDate->format('W') }}, {{ $currentDate->format('Y') }}
                    @else
                        {{ $currentDate->format('F Y') }}
                    @endif
                </h2>
                <a href="?date={{ now()->format('Y-m-d') }}&view={{ $view }}" 
                   class="inline-flex items-center px-4 py-2 border-2 border-blue-500 rounded-md text-sm font-medium text-blue-600 bg-white hover:bg-blue-50">
                    Today
                </a>
            </div>
            
            <!-- Right: Period navigation + Year -->
            <div class="flex items-center space-x-2">
                <a href="?date={{ $nextPeriod->format('Y-m-d') }}&view={{ $view }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    {{ $view === 'week' ? 'Week' : 'Month' }} →
                </a>
                <a href="?date={{ $nextYear->format('Y-m-d') }}&view={{ $view }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    ⏩
                </a>
            </div>
        </div>
        
        <!-- View switcher (mobile friendly) -->
        <div class="flex justify-center mt-4 space-x-2 sm:hidden">
            <a href="?date={{ $currentDate->format('Y-m-d') }}&view=week" 
               class="px-4 py-2 text-sm font-medium rounded-md {{ $view === 'week' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Week
            </a>
            <a href="?date={{ $currentDate->format('Y-m-d') }}&view=month" 
               class="px-4 py-2 text-sm font-medium rounded-md {{ $view === 'month' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Month
            </a>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <table class="min-w-full divide-y divide-gray-200">
                <!-- Header: Day numbers and names -->
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <!-- Property column header -->
                        <th scope="col" class="sticky left-0 z-20 bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-r border-gray-200 min-w-[120px]">
                            Property
                        </th>
                        
                        <!-- Day columns -->
                        @foreach($days as $day)
                        <th scope="col" class="px-1 py-2 text-center min-w-[40px] {{ $day->isToday() ? 'bg-blue-100' : '' }}">
                            <div class="text-xs font-medium text-gray-700">
                                {{ $day->format('D') }}
                            </div>
                            <div class="text-sm font-bold {{ $day->isToday() ? 'text-blue-600' : 'text-gray-900' }}">
                                {{ $day->format('j') }}
                            </div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($properties as $property)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <!-- Property name (sticky) -->
                        <td class="sticky left-0 z-10 bg-white px-4 py-3 border-r border-gray-200 group-hover:bg-gray-50">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $property->color }}"></div>
                                <span class="font-medium text-gray-900 text-sm whitespace-nowrap">{{ $property->name }}</span>
                            </div>
                        </td>
                        
                        <!-- Day cells with bookings -->
                        @foreach($days as $dayIndex => $day)
                        <td class="relative h-16 px-0 {{ $day->isToday() ? 'bg-blue-50' : '' }}" style="width: 40px;">
                            <!-- Bookings overlapping this day -->
                            @foreach($property->bookings as $booking)
                                @php
                                    // Check if booking overlaps this day
                                    // Booking occupies nights, so check_in day at noon to check_out day at noon
                                    $checkIn = $booking->check_in;
                                    $checkOut = $booking->check_out;
                                    
                                    $isFirstDay = $checkIn->isSameDay($day);
                                    $isLastDay = $checkOut->isSameDay($day);
                                    $isMiddleDay = $day->between($checkIn, $checkOut) && !$isFirstDay && !$isLastDay;
                                    
                                    $shouldDisplay = $isFirstDay || $isMiddleDay;
                                @endphp
                                
                                @if($shouldDisplay)
                                    @php
                                        // Calculate position and width
                                        $startOffset = $isFirstDay ? 50 : 0; // Start at noon on check-in day
                                        
                                        // Calculate how many days until check-out
                                        $daysUntilCheckout = $day->diffInDays($checkOut);
                                        $width = ($daysUntilCheckout * 100) + 50; // Full days + half day
                                        
                                        if (!$isFirstDay) {
                                            $width += 50; // Add half day at start if continuing from previous
                                            $startOffset = 0;
                                        }
                                    @endphp
                                    
                                    <div @click="showBooking({{ $booking->id }})"
                                         class="absolute top-1 bottom-1 rounded cursor-pointer text-white text-xs px-2 py-1 overflow-hidden hover:shadow-lg hover:z-10 transition-shadow flex items-center"
                                         style="left: {{ $startOffset }}%; 
                                                width: {{ $width }}%; 
                                                background-color: {{ $property->color }}; 
                                                opacity: 0.9"
                                         title="{{ $booking->guest_name }} ({{ $booking->check_in->format('M j') }} - {{ $booking->check_out->format('M j') }})">
                                        @if($isFirstDay)
                                            <span class="font-semibold truncate">{{ $booking->guest_name }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
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
                        <div class="flex items-center">
                            <div class="w-4 h-4 rounded-full mr-3" :style="`background-color: ${selectedBooking.property?.color || '#999'}`"></div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Property:</span>
                                <span class="text-sm text-gray-900 ml-2" x-text="selectedBooking.property?.name"></span>
                            </div>
                        </div>
                        
                        <div class="border-t pt-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-xs font-medium text-gray-500 uppercase">Check-in</span>
                                    <div class="text-sm text-gray-900 font-semibold mt-1" x-text="formatDate(selectedBooking.check_in)"></div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-gray-500 uppercase">Check-out</span>
                                    <div class="text-sm text-gray-900 font-semibold mt-1" x-text="formatDate(selectedBooking.check_out)"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="border-t pt-3">
                            <span class="text-sm font-medium text-gray-500">Source:</span>
                            <span class="text-sm text-gray-900 ml-2" x-text="selectedBooking.source_name"></span>
                        </div>
                        
                        <div class="border-t pt-3">
                            <span class="text-sm font-medium text-gray-500">Nights:</span>
                            <span class="text-sm text-gray-900 ml-2" x-text="calculateNights(selectedBooking.check_in, selectedBooking.check_out)"></span>
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
        },
        
        calculateNights(checkIn, checkOut) {
            const start = new Date(checkIn);
            const end = new Date(checkOut);
            const nights = Math.floor((end - start) / (1000 * 60 * 60 * 24));
            return `${nights} night${nights !== 1 ? 's' : ''}`;
        }
    }
}
</script>
@endsection
