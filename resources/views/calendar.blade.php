@extends('layouts.app')

@section('title', 'Calendar - Bokit')

@section('styles')
@vite('resources/css/calendar.css')
@endsection

@section('content')
<div x-data="calendar()" x-cloak>
    <!-- Navigation Bar -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-2 sm:p-4 mb-6">
        <div class="flex items-center justify-between gap-2">
            <!-- Left: Navigation + Today button -->
            <div class="flex items-center space-x-1">
                <a href="?date={{ $prevYear->format('Y-m-d') }}&view={{ $view }}"
                   class="inline-flex items-center px-2 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    «
                </a>
                <a href="?date={{ $prevPeriod->format('Y-m-d') }}&view={{ $view }}"
                   class="inline-flex items-center px-2 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    ‹
                </a>
                <a href="{{ $view !== 'month' ? route('calendar', ['view' => $view]) : route('calendar') }}"
                   class="inline-flex items-center px-2 sm:px-3 py-2 border-2 border-blue-500 rounded-md text-sm font-medium text-blue-600 bg-white hover:bg-blue-50">
                    <span class="hidden sm:inline">{{ __('app.today') }}</span>
                    <span class="sm:hidden">🏠</span>
                </a>
            </div>

            <!-- Center: Current period -->
            <div class="flex flex-col items-center min-w-0 flex-1">
                <h2 class="text-lg sm:text-2xl font-bold text-gray-900 truncate">
                    @if($view === 'week')
                        {{ $startDate->format('M j') }} - {{ $endDate->format('j, Y') }}
                    @elseif($view === '2weeks')
                        {{ $startDate->format('M j') }} - {{ $endDate->format('j, Y') }}
                    @else
                        {{ $currentDate->format('F Y') }}
                    @endif
                </h2>
                <div class="text-xs text-gray-500 mt-1">
                    @if($view === 'week')
                        Week {{ $startDate->format('W') }}
                    @elseif($view === '2weeks')
                        Weeks {{ $startDate->format('W') }}-{{ $endDate->format('W') }}
                    @else
                        @php
                            $firstWeek = $startDate->format('W');
                            $lastWeek = $endDate->format('W');
                            // Handle year transition
                            if ($lastWeek < $firstWeek) {
                                $lastWeek = $endDate->copy()->endOfMonth()->format('W');
                            }
                        @endphp
                        Weeks {{ $firstWeek }}-{{ $lastWeek }}
                    @endif
                </div>
            </div>

            <!-- Right: Period navigation + Year -->
            <div class="flex items-center space-x-1">
                @if($canNavigateForward)
                    <a href="?date={{ $nextPeriod->format('Y-m-d') }}&view={{ $view }}"
                       class="inline-flex items-center px-2 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        ›
                    </a>
                @else
                    <span class="inline-flex items-center px-2 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                        ›
                    </span>
                @endif

                @if($canNavigateYearForward)
                    <a href="?date={{ $nextYear->format('Y-m-d') }}&view={{ $view }}"
                       class="inline-flex items-center px-2 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        »
                    </a>
                @else
                    <span class="inline-flex items-center px-2 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                        »
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Calendar Grid - Full width -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <table class="min-w-full divide-y divide-gray-200" style="table-layout: fixed;">
                <!-- Header: Day numbers and names -->
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <!-- Unit column header -->
                        <th scope="col" class="property-column sticky left-0 z-20 bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-r-2 border-gray-300 min-w-[100px] w-[120px] max-sm:hidden">
                            Unit
                        </th>

                        <!-- Day columns with vertical separators -->
                        @foreach($days as $day)
                        <th scope="col" class="px-1 py-2 text-center min-w-[38px] border-r border-gray-200 relative
                            {{ $day->isToday() ? 'bg-blue-50/50' : '' }}">
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
                        <!-- Property Header Row -->
                        <tr class="bg-gray-100 border-t-2 border-gray-300">
                            <td class="property-column sticky left-0 z-10 bg-gray-100 px-4 py-2 border-r-2 border-gray-300 max-sm:hidden">
                                <span class="font-bold text-gray-800 text-sm">{{ $property->name }}</span>
                            </td>
                            @foreach($days as $day)
                            <td class="bg-gray-100 border-r border-gray-200"></td>
                            @endforeach
                        </tr>

                        <!-- Units of this Property -->
                        @foreach($property->units as $unit)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <!-- Unit name (sticky) -->
                            <td class="property-column sticky left-0 z-10 bg-white px-4 py-3 border-r-2 border-gray-300 hover:bg-gray-50 max-sm:hidden">
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $unit->color }}"></div>
                                    <span class="font-medium text-gray-900 text-sm whitespace-nowrap">{{ $unit->name }}</span>
                                </div>
                            </td>

                            <!-- Day cells with bookings -->
                            @foreach($days as $dayIndex => $day)
                            <td class="relative h-16 max-sm:h-20 px-0 border-r border-gray-200">
                                <!-- Unit label (mobile only) -->
                                @if($dayIndex === 0)
                                <div class="hidden max-sm:block absolute top-0.5 left-0.5 text-[0.6rem] font-semibold z-[5] px-1 py-0.5 rounded" style="background-color: {{ $unit->color }}; color: white; opacity: 0.9;">
                                    {{ $unit->name }}
                                </div>
                                @endif
                                <!-- Background highlight for today (behind bookings) -->
                                @if($day->isToday())
                                <div class="absolute inset-0 bg-blue-50 opacity-40 pointer-events-none max-sm:!top-[1.75rem]"></div>
                                @endif

                                <!-- Bookings overlapping this day -->
                                @php
                                    // Debug: count bookings by status
                                    $bookingStatuses = $unit->bookings->pluck('status')->countBy();
                                    // \Log::info("Unit {$unit->name} has bookings:", $bookingStatuses->toArray());
                                @endphp
                                @foreach($unit->bookings as $booking)
                                @php
                                    // Debug: show all bookings including their status
                                    if ($booking->status === 'cancelled' || $booking->status === 'vanished') {
                                        // Log these bookings to see if they are being processed
                                        // \Log::info("Processing booking: {$booking->guest_name} - {$booking->status}");
                                    }
                                @endphp
                                @php
                                    // Real check-in/check-out dates (hotel format)
                                    $checkIn = $booking->check_in;
                                    $checkOut = $booking->check_out;

                                    $startsBeforePeriod = $checkIn->lt($startDate);
                                    $endsAfterPeriod = $checkOut->gt($endDate);

                                    // Determine if this is the first visible day for this booking
                                    $isFirstVisibleDay = ($checkIn->isSameDay($day)) || ($startsBeforePeriod && $day->isSameDay($startDate));

                                    // Display block from check-in noon to check-out noon
                                    $shouldDisplay = $day->gte($checkIn) && $day->lt($checkOut);
                                @endphp

                                @if($shouldDisplay && $isFirstVisibleDay)
                                    @php
                                        // Calculate position and width
                                        $isActualFirstDay = $checkIn->isSameDay($day);

                                        // Calculate the END of the visible block (not checkout!)
                                        // If booking extends beyond visible period, block ends at end of last visible day
                                        // Otherwise, block ends at checkout (noon of checkout day)
                                        $blockEndDate = $endsAfterPeriod ? $endDate : $checkOut;
                                        $daysToEnd = $day->diffInDays($blockEndDate);

                                        // CRITICAL: Limit to remaining visible days to prevent overflow
                                        $remainingDays = count($days) - $dayIndex - 1;
                                        // $daysToEnd = min($daysToEnd, $remainingDays);

                                        $extend = ($startsBeforePeriod ? 0.5 : 0) + ($endsAfterPeriod ? 0.5 : 0);
                                        $dayBlocks = $daysToEnd + $extend;

                                        // Calculate width
                                        $leftPercent = $isActualFirstDay ? 50 : 0;
                                        $widthPercent = $dayBlocks * 100;
                                    @endphp

                                    @php
                                        // Don't apply additional opacity for cancelled/vanished bookings
                                        // as their color already includes opacity
                                        $applyOpacity = !in_array($booking->status, ['cancelled', 'vanished', 'deleted']);
                                        $opacityStyle = $applyOpacity ? 'opacity: 0.92;' : '';
                                    @endphp
                                    <div class="absolute rounded-xl text-white text-xs font-medium overflow-hidden hover:shadow-xl hover:opacity-100 transition-all flex items-center px-2 cursor-pointer z-10 max-sm:!top-[1.75rem]"
                                         style="left: {{ $leftPercent }}%;
                                                width: {{ $widthPercent }}%;
                                                top: 0.375rem;
                                                bottom: 0.375rem;
                                                margin-left: 2px;
                                                margin-right: 2px;
                                                background-color: {{ $booking->color }};
                                                {{ $opacityStyle }}"
                                         @click="showBooking({{ $booking->id }})">
                                        @if($startsBeforePeriod)
                                            <span class="opacity-75 mr-1">◀</span>
                                        @endif
                                        <span class="truncate text-lg flex-2">
                                            @if($booking->trashed())
                                                <span class="font-bold text-xs bg-black bg-opacity-50 px-1 rounded">DELETED</span>
                                            @elseif(in_array($booking->status, ['cancelled', 'vanished', 'deleted']))
                                                <span class="font-bold text-xs bg-black bg-opacity-50 px-1 rounded">{{ strtoupper($booking->status) }}</span>
                                            @endif
                                            {{ $booking->guest_name }}
                                        </span>
                                        @if($endsAfterPeriod)
                                            <span class="opacity-75 ms-auto mr-">▶</span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </td>
                        @endforeach
                        </tr>
                        @endforeach
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
                    <!-- Title: Guest name -->
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-xl font-bold text-gray-900">
                            <span x-show="selectedBooking.deleted_at" class="inline-block bg-red-600 text-white text-xs font-bold px-2 py-1 rounded mr-2">DELETED</span>
                            <span x-text="selectedBooking.guest_name"></span>
                        </h3>
                        <button @click="selectedBooking = null" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <!-- Unit + Status -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-900" x-text="selectedBooking.unit?.name"></span>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded text-white text-xs font-semibold"
                                  :style="`background-color: ${selectedBooking.color}`"
                                  x-text="selectedBooking.status_label"></span>
                        </div>

                        <!-- Check-in / Check-out / Nights (une seule rangée) -->
                        <div class="border-t pt-3">
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <span class="text-xs font-medium text-gray-500 uppercase block">Check-in</span>
                                    <div class="text-sm text-gray-900 font-semibold mt-1" x-text="formatDate(selectedBooking.check_in)"></div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-gray-500 uppercase block">Check-out</span>
                                    <div class="text-sm text-gray-900 font-semibold mt-1" x-text="formatDate(selectedBooking.check_out)"></div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-gray-500 uppercase block">Nights</span>
                                    <div class="text-sm text-gray-900 font-semibold mt-1" x-text="calculateNights(selectedBooking.check_in, selectedBooking.check_out)"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Guests / Adults / Children -->
                        <div x-show="selectedBooking.raw_data?.guests || selectedBooking.adults || selectedBooking.children" class="border-t pt-3">
                            <div x-show="selectedBooking.raw_data?.guests" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Guests:</span>
                                <span class="text-gray-900 ml-2" x-text="selectedBooking.raw_data?.guests"></span>
                            </div>
                            <div x-show="selectedBooking.adults" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Adults:</span>
                                <span class="text-gray-900 ml-2" x-text="selectedBooking.adults"></span>
                            </div>
                            <div x-show="selectedBooking.children" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Children:</span>
                                <span class="text-gray-900 ml-2" x-text="selectedBooking.children"></span>
                            </div>
                        </div>

                        <!-- Phone / Mobile / Country / Arrival time -->
                        <div>
                            <div x-show="selectedBooking.raw_data?.phone" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Phone:</span>
                                <a :href="'tel:' + selectedBooking.raw_data?.phone" class="text-blue-600 hover:underline ml-2" x-text="selectedBooking.raw_data?.phone"></a>
                            </div>
                            <div x-show="selectedBooking.raw_data?.mobile" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Mobile:</span>
                                <a :href="'tel:' + selectedBooking.raw_data?.mobile" class="text-blue-600 hover:underline ml-2" x-text="selectedBooking.raw_data?.mobile"></a>
                            </div>
                            <div x-show="selectedBooking.raw_data?.email" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Email:</span>
                                <a :href="'mailto:' + selectedBooking.raw_data?.email" class="text-blue-600 hover:underline ml-2" x-text="selectedBooking.raw_data?.email"></a>
                            </div>
                            <div x-show="selectedBooking.raw_data?.country" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Country:</span>
                                <span class="text-gray-900 ml-2" x-text="selectedBooking.raw_data?.country"></span>
                            </div>
                            <div x-show="selectedBooking.raw_data?.arrival_time" class="text-sm mb-1">
                                <span class="font-medium text-gray-500">Arrival time:</span>
                                <span class="text-gray-900 ml-2" x-text="selectedBooking.raw_data?.arrival_time"></span>
                            </div>
                        </div>

                        <!-- Guest Comments -->
                        <div x-show="selectedBooking.raw_data?.guest_comments" class="border-t pt-3">
                            <span class="text-sm font-medium text-gray-500 block mb-2">Guest comments:</span>
                            <div class="text-sm text-gray-700 bg-gray-50 p-3 rounded whitespace-pre-wrap" x-text="selectedBooking.raw_data?.guest_comments"></div>
                        </div>

                        <!-- Notes (unprocessed data) -->
                        <div x-show="selectedBooking.notes" class="border-t pt-3">
                            <span class="text-sm font-medium text-gray-500 block mb-2">Notes:</span>
                            <div class="text-sm text-gray-700 bg-gray-50 p-3 rounded whitespace-pre-wrap" x-text="selectedBooking.notes"></div>
                        </div>

                        <!-- Source + API Source -->
                        <div class="border-t pt-3">
                            <div class="text-sm mb-1">
                                <span class="font-medium text-gray-500">{{ __('Source:') }}</span>
                                <span class="text-gray-900 ml-2" x-text="selectedBooking.source_name"></span>
                                <span class="text-gray-900 ml-2">
                                    <span x-text="selectedBooking.raw_data?.api_source"></span>
                                    <span x-show="selectedBooking.raw_data?.api_ref" x-text="' ' + selectedBooking.raw_data?.api_ref"></span>
                                </span>
                            </div>
                            <div class="text-sm mb-1">
                                <span class="font-medium text-gray-500">{{ __('Link:') }}</span>
                                <span x-html="' ' + selectedBooking.ota_link" class="text-blue-600 hover:underline ml-2"></span>
                            </div>
                            <div class="text-sm mb-1">
                                <span class="font-medium text-gray-500">{{ __('URL:') }}</span>
                                <span x-text="' ' + selectedBooking.ota_url"></span>
                            </div>
                        </div>

                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
// Auto-adjust view based on viewport width
(function() {
    function getOptimalView() {
        const width = window.innerWidth;
        if (width < 640) return 'week';
        if (width < 1280) return '2weeks';
        return 'month';
    }

    function checkAndRedirect() {
        const urlParams = new URLSearchParams(window.location.search);
        const currentView = urlParams.get('view') || 'month';
        const optimalView = getOptimalView();

        if (currentView !== optimalView) {
            urlParams.set('view', optimalView);
            window.location.search = urlParams.toString();
        }
    }

    // Check on load
    checkAndRedirect();

    // Check on resize (debounced)
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(checkAndRedirect, 250);
    });
})();

function calendar() {
    return {
        selectedBooking: null,
        baseUrl: '{{ url('/') }}',
        locale: '{{ app()->getLocale() }}',

        async showBooking(bookingId) {
            try {
                const response = await fetch(`${this.baseUrl}/booking/${bookingId}`);
                this.selectedBooking = await response.json();
            } catch (error) {
                console.error('Failed to load booking:', error);
            }
        },

        formatDate(dateString) {
            // Handle both "YYYY-MM-DD" and "YYYY-MM-DD HH:MM:SS" formats
            const parts = dateString.split(/[T ]/);
            const [year, month, day] = parts[0].split('-');
            const date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
            // Use short numeric format: 12/17/2025 (en-US) or 17/12/2025 (fr-FR)
            return date.toLocaleDateString(this.locale, {
                year: 'numeric',
                month: 'numeric',
                day: 'numeric'
            });
        },

        calculateNights(checkIn, checkOut) {
            const start = new Date(checkIn);
            const end = new Date(checkOut);
            const nights = Math.floor((end - start) / (1000 * 60 * 60 * 24));
            return `${nights}`;
        }
    }
}
</script>
@endsection
