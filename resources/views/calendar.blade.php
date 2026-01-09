@extends('layouts.app')

@section('title', __('app.calendar'))

@section('styles')
@vite('resources/css/calendar.css')
@endsection

@php
use App\Traits\TimezoneTrait;
@endphp

@section('content')
<div x-data="calendar()" class="full-width" x-cloak>
    <!-- Navigation Bar -->
    <div class="calendar-nav my-auto">
        <div class="nav-controls">
            <!-- Left: Navigation + Today button -->
            <div class="nav-left">
                <a href="?date={{ $prevYear->format('Y-m-d') }}&view={{ $view }}"
                   class="nav-button">
                    «
                </a>
                <a href="?date={{ $prevPeriod->format('Y-m-d') }}&view={{ $view }}"
                   class="nav-button">
                    ‹
                </a>
                <a href="{{ $view !== 'month' ? route('calendar', ['view' => $view]) : route('calendar') }}"
                   class="nav-button today">
                    <span class="text-desktop-only">{{ __('app.today') }}</span>
                    <span class="text-mobile-only">🏠</span>
                </a>
            </div>

            <!-- Center: Current period -->
            <div class="period">
                <div class="week-info">
                    @if($view === 'week')
                        {{ __('app.week') }} {{ $startDate->translatedFormat('W Y') }}
                    @else
                        {{ __('app.weeks') }} {{ $startDate->translatedFormat('W') }}-{{ $endDate->translatedFormat('W') }}
                    @endif
                </div>
                <h2>
                    @if($view === 'week')
                        {{ ucfirst(TimezoneTrait::dateRange($startDate, $endDate, 'short')) }}
                    @elseif($view === '2weeks')
                        {{ ucfirst(TimezoneTrait::dateRange($startDate, $endDate, 'medium')) }}
                    @else
                        {{ ucfirst($currentDate->translatedFormat('F Y')) }}
                    @endif
                </h2>
                @if($view === 'week')
                    <div class="timezone">{{ $displayTimezoneShort }}</div>
                @else
                    <div class="timezone">{{ $displayTimezone }}</div>
                @endif
            </div>

            <!-- Right: Period navigation + Year -->
            <div class="nav-right">
                @if($canNavigateForward)
                    <a href="?date={{ $nextPeriod->format('Y-m-d') }}&view={{ $view }}"
                       class="nav-button">
                        ›
                    </a>
                @else
                    <span class="nav-button disabled">
                        ›
                    </span>
                @endif

                @if($canNavigateYearForward)
                    <a href="?date={{ $nextYear->format('Y-m-d') }}&view={{ $view }}"
                       class="nav-button">
                        »
                    </a>
                @else
                    <span class="nav-button disabled">
                        »
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Calendar Grid - Full width -->
    <div class="calendar-wrapper">
        <div class="calendar-table-container">
            <table class="calendar-table">
                <!-- Header: Day numbers and names -->
                <thead class="calendar-header">
                    <tr>
                        <!-- Unit column header -->
                        <th scope="col" class="unit-column">
                            Unit
                        </th>

                        <!-- Day columns with vertical separators -->
                        @foreach($days as $day)
                        <th scope="col" class="day-column {{ $day->isToday() ? 'today' : '' }} {{ $day->lt(today()) ? 'past' : '' }} {{ $day->isWeekend() ? 'weekend' : '' }}">
                            <div class="day-name">
                                {{ $day->translatedFormat('D') }}
                            </div>
                            <div class="day-number {{ $day->isToday() ? 'today' : '' }}">
                                {{ $day->translatedFormat('j') }}
                            </div>
                        </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="calendar-body">
                    @foreach($properties as $property)
                        @php
                            $isSingleUnit = $property->units->count() === 1;
                        @endphp

                        <!-- Property Header Row (only for multi-unit properties) -->
                        @if(!$isSingleUnit)
                        <tr class="property-row">
                            <td class="property-name">
                                <span>{{ $property->name }}</span>
                                @if($property->timezone() !== $displayTimezone)
                                    <span class="timezone">{{ $property->timezone(true) }}</span>
                                @endif
                            </td>
                            @foreach($days as $day)
                            <td class="property-spacer {{ $day->isPast() ? 'past' : '' }} {{ $day->isWeekend() ? 'weekend' : '' }}"></td>
                            @endforeach
                        </tr>
                        @endif

                        <!-- Units of this Property -->
                        @foreach($property->units as $unit)
                        <tr class="{{ $isSingleUnit ? 'property-row' : '' }} unit-row">
                            <!-- Unit name (sticky) -->
                            <td class="{{ $isSingleUnit ? 'property-name' : 'unit-cell' }}">
                                <div class="unit-info">
                                    <span class="unit-name">{{ $unit->name }}</span>
                                    @if($unit->timezone() !== $property->timezone())
                                        <span class="timezone">{{ $unit->timezone(true) }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Day cells with bookings -->
                            @foreach($days as $dayIndex => $day)
                            <td class="day-cell {{ $day->lt(today()) ? 'past' : '' }} {{ $day->isWeekend() ? 'weekend' : '' }}">
                                <!-- Unit label (mobile only) -->
                                @if($dayIndex === 0)
                                <div class="unit-label-mobile">
                                    {{ $unit->name }}
                                    @if($unit->timezone() !== $property->timezone())
                                        <span class="timezone">({{ $unit->timezone(true) }})</span>
                                    @endif
                                </div>
                                @endif
                                <!-- Background highlight for today (behind bookings) -->
                                @if($day->isToday())
                                <div class="today-highlight"></div>
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
                                    $continued = $startsBeforePeriod ? 'continued' : '';
                                    $continues = $endsAfterPeriod ? 'continues' : '';
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
                                    <div class="booking-block status-{{ $booking->status }} text-primary bg-{{ $booking->status }} {{ $continued }} {{ $continues }}"
                                         style="left: {{ $leftPercent }}%;
                                                width: {{ $widthPercent }}%;"
                                         @click="showBooking({{ $booking->id }})">
                                        {{-- @if($startsBeforePeriod)
                                            <span class="continues">◀</span>
                                        @endif --}}
                                        <span class="guest-name">
                                            {{ $booking->guest_name }}
                                        </span>
                                        @if($booking->api_source && $booking->api_source != 'beds24')
                                        <span class="badge badge-ota ota-{{ $booking->api_source }}">
                                            {{ $booking->api_source }}
                                        </span>
                                        @endif
                                        {{-- @if($endsAfterPeriod)
                                            <span class="extends">▶</span>
                                        @endif --}}
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
         class="modal-backdrop">
        <div class="modal card p-0" @click.stop>
            <template x-if="selectedBooking">
                <div>
                    <!-- Title: Guest name -->
                    <div class="modal-header card-header"
                         :class="'status-' + (selectedBooking?.status || '') + ' bg-' + (selectedBooking?.status || '')">
                    {{-- <div class="modal-header card-header"> --}}
                        <h3>
                            <span x-show="selectedBooking.deleted_at" class="badge-deleted">DELETED</span>
                            <span x-text="selectedBooking.guest_name"></span>
                        </h3>
                        <button @click="selectedBooking = null" class="close-button text-white">
                            {!! icon('close') !!}
                        </button>
                    </div>

                    <div class="modal-content card-body">
                        <!-- Unit + Status -->
                        <div class="fields-row">
                            <div class="unit-info">
                                <span class="unit-name" x-text="selectedBooking.unit?.name"></span>
                                <span class="actions action-links" x-html="' ' + selectedBooking.actions"></span>
                            </div>
                        </div>

                        <!-- Check-in / Check-out / Nights (une seule rangée) -->
                        <div class="dates-section">
                            <div class="date-field">
                                <label>Check-in</label>
                                <div class="value" x-text="formatDate(selectedBooking.check_in)"></div>
                            </div>
                            <div class="date-field">
                                <label>Check-out</label>
                                <div class="value" x-text="formatDate(selectedBooking.check_out)"></div>
                            </div>
                            <div class="date-field">
                                <label>Nights</label>
                                <div class="value" x-text="calculateNights(selectedBooking.check_in, selectedBooking.check_out)"></div>
                            </div>
                        </div>

                        <!-- Guests / Adults / Children -->
                        <div x-show="selectedBooking.metadata?.guests || selectedBooking.adults || selectedBooking.children" class="detail-section">
                            <div x-show="selectedBooking.metadata?.guests" class="detail-line">
                                <span class="label">Guests:</span>
                                <span class="value" x-text="selectedBooking.metadata?.guests"></span>
                            </div>
                            <div x-show="selectedBooking.adults" class="detail-line">
                                <span class="label">Adults:</span>
                                <span class="value" x-text="selectedBooking.adults"></span>
                            </div>
                            <div x-show="selectedBooking.children" class="detail-line">
                                <span class="label">Children:</span>
                                <span class="value" x-text="selectedBooking.children"></span>
                            </div>
                        </div>

                        <!-- Phone / Mobile / Country / Arrival time -->
                        <div class="detail-section">
                            <div x-show="selectedBooking.metadata?.phone" class="detail-line">
                                <span class="label">Phone:</span>
                                <a :href="'tel:' + selectedBooking.metadata?.phone" class="link" x-text="selectedBooking.metadata?.phone"></a>
                            </div>
                            <div x-show="selectedBooking.metadata?.mobile" class="detail-line">
                                <span class="label">Mobile:</span>
                                <a :href="'tel:' + selectedBooking.metadata?.mobile" class="link" x-text="selectedBooking.metadata?.mobile"></a>
                            </div>
                            <div x-show="selectedBooking.metadata?.email" class="detail-line">
                                <span class="label">Email:</span>
                                <a :href="'mailto:' + selectedBooking.metadata?.email" class="link" x-text="selectedBooking.metadata?.email"></a>
                            </div>
                            <div x-show="selectedBooking.metadata?.country" class="detail-line">
                                <span class="label">Country:</span>
                                <span class="value" x-text="selectedBooking.metadata?.country"></span>
                            </div>
                            <div x-show="selectedBooking.metadata?.arrival_time" class="detail-line">
                                <span class="label">Arrival time:</span>
                                <span class="value" x-text="selectedBooking.metadata?.arrival_time"></span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div x-show="selectedBooking.notes" class="comments-section">
                            <label>Notes:</label>
                            <div class="comments-text" x-text="selectedBooking.notes"></div>
                        </div>

                        <!-- Guest Comments -->
                        <div x-show="selectedBooking.metadata?.ota_comments" class="comments-section">
                            <label>{{ __('booking.field.comments') }}</label>
                            <div class="comments-text" x-text="selectedBooking.metadata?.ota_comments"></div>
                        </div>


                        <!-- Description (unprocessed data) -->
                        <div x-show="selectedBooking.metadata?.description" class="comments-section">
                            <label>Description:</label>
                            <div class="comments-text" x-text="selectedBooking.metadata?.description"></div>
                        </div>
                    </div>
                    <div class="modal-footer card-footer">
                        <div class="source-line">
                            <span class="label">{{ __('Source:') }}</span>
                            <span class="value" x-text="selectedBooking.source_name"></span>
                            <span class="value">
                                <span x-text="selectedBooking.metadata?.api_source"></span>
                                <span x-show="selectedBooking.metadata?.api_ref" x-text="' ' + selectedBooking.metadata?.api_ref"></span>
                            </span>
                        </div>
                        {{-- <div class="actions action-links">
                            <span class="action-link">
                                <span x-html="' ' + selectedBooking.ota_link" class="link"></span>
                            </span>
                        </div> --}}
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
