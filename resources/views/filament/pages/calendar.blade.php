@php
    use App\Filament\Pages\Calendar;
    use App\Traits\TimezoneTrait;
@endphp
<x-filament-panels::page>
    @vite('resources/css/calendar.css')

    <div x-data="calendar()" class="full-width" x-cloak>

        <!-- Resync overlay: shown while pulling the edited booking back in -->
        <div x-show="resyncing" x-cloak
             style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.4);">
            <div style="background:#fff;padding:1rem 1.5rem;border-radius:.5rem;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,.15);">
                {{ __('app.syncing') }}…
            </div>
        </div>
        <!-- Navigation Bar -->
        <div class="calendar-nav my-auto">
            <div class="nav-controls">
                <!-- Left: Navigation + Today button -->
                <div class="nav-left">
                    <a href="?date={{ $prevYear->format('Y-m-d') }}&view={{ $viewType }}"
                       class="nav-button">
                        «
                    </a>
                    <a href="?date={{ $prevPeriod->format('Y-m-d') }}&view={{ $viewType }}"
                       class="nav-button">
                        ‹
                    </a>
                    <a href="{{ Calendar::getUrl(array_filter(['view' => $viewType !== 'month' ? $viewType : null, 'cancelled' => $showCancelled ? 1 : null, 'quotes' => $showQuotes ? null : 0], fn ($v) => $v !== null)) }}"
                       class="nav-button today">
                        <span class="text-desktop-only">{{ __('app.today') }}</span>
                        <span class="text-mobile-only">🏠</span>
                    </a>
                </div>

                <!-- Center: Current period -->
                <div class="period">
                    <div class="week-info">
                        @if($viewType === 'week')
                            {{ __('app.week') }} {{ $startDate->translatedFormat('W Y') }}
                        @else
                            {{ __('app.weeks') }} {{ $startDate->translatedFormat('W') }}-{{ $endDate->translatedFormat('W') }}
                        @endif
                    </div>
                    <h2>
                        @if($viewType === 'week')
                            {{ ucfirst(TimezoneTrait::dateRange($startDate, $endDate, 'short')) }}
                        @elseif($viewType === '2weeks')
                            {{ ucfirst(TimezoneTrait::dateRange($startDate, $endDate, 'medium')) }}
                        @else
                            {{ ucfirst($currentDate->translatedFormat('F Y')) }}
                        @endif
                    </h2>
                    @if($viewType === 'week')
                        <div class="timezone">{{ $displayTimezoneShort }}</div>
                    @else
                        <div class="timezone">{{ $displayTimezone }}</div>
                    @endif
                </div>

                <!-- Right: Display toggles + Period navigation + Year -->
                <div class="nav-right">
                    @php
                        $toggleBase = '?date='.$currentDate->format('Y-m-d').'&view='.$viewType;
                    @endphp
                    <a href="{{ $toggleBase.($showCancelled ? '' : '&cancelled=1').($showQuotes ? '' : '&quotes=0') }}"
                       class="nav-button toggle {{ $showCancelled ? 'active' : '' }}"
                       title="{{ __('booking.filter.show_cancelled') }}"
                       aria-pressed="{{ $showCancelled ? 'true' : 'false' }}">
                        {!! icon('calendar-x') !!}
                    </a>
                    <a href="{{ $toggleBase.($showCancelled ? '&cancelled=1' : '').($showQuotes ? '&quotes=0' : '') }}"
                       class="nav-button toggle {{ $showQuotes ? 'active' : '' }}"
                       title="{{ __('booking.filter.show_quotes') }}"
                       aria-pressed="{{ $showQuotes ? 'true' : 'false' }}">
                        {!! icon('calculator') !!}
                    </a>

                    @if($canNavigateForward)
                        <a href="?date={{ $nextPeriod->format('Y-m-d') }}&view={{ $viewType }}{{ $filterQuery }}"
                           class="nav-button">
                            ›
                        </a>
                    @else
                        <span class="nav-button disabled">
                            ›
                        </span>
                    @endif

                    @if($canNavigateYearForward)
                        <a href="?date={{ $nextYear->format('Y-m-d') }}&view={{ $viewType }}{{ $filterQuery }}"
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

                            <!-- Property Header Row: grouping only helps when
                                 several properties are displayed -->
                            @if($properties->count() > 1 && !$isSingleUnit)
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
                                    @foreach($unit->bookings as $booking)
                                    @php
                                    try {
                                        // Real check-in/check-out dates (hotel format)
                                        $checkIn = $booking->check_in;
                                        $checkOut = $booking->check_out;

                                        $startsBeforePeriod = $checkIn->lt($startDate);
                                        $endsAfterPeriod = $checkOut->gt($endDate);

                                        $continued = $startsBeforePeriod ? 'continued' : '';
                                        $continues = $endsAfterPeriod ? 'continues' : '';
                                        // Determine if this is the first visible day for this booking

                                        $isFirstVisibleDay = ($checkIn->isSameDay($day)) || ($startsBeforePeriod && $day->isSameDay($startDate));
                                    } catch (\Error $e) {
                                        notice($e->getMessage(), 'error');
                                        continue;
                                    }
                                    @endphp

                                    @if($isFirstVisibleDay)
                                        @php
                                            // Calculate position and width
                                            $isActualFirstDay = $checkIn->isSameDay($day);

                                            // Calculate the END of the visible block (not checkout!)
                                            // If booking extends beyond visible period, block ends at end of last visible day
                                            // Otherwise, block ends at checkout (noon of checkout day)
                                            $blockEndDate = $endsAfterPeriod ? $endDate : $checkOut;
                                            $daysToEnd = $day->diffInDays($blockEndDate);

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
                                        <div class="booking-block status-{{ $booking->displayStatus() }} text-primary bg-{{ $booking->displayStatus() }} {{ $continued }} {{ $continues }}"
                                             style="left: {{ $leftPercent }}%;
                                                    width: {{ $widthPercent }}%;"
                                             @click="showBooking({{ $booking->id }})">
                                            <span class="guest-name">
                                                {{ $booking->guest_name }}
                                            </span>
                                            @if($booking->getMetadata('is_new'))
                                            <span class="status-badge">{{ __('booking.tag.new') }}</span>
                                            @endif
                                            @php $otaLogo = $booking->api_source !== 'beds24' ? icon_ota($booking->api_source) : null; @endphp
                                            @if($otaLogo)
                                            <span class="badge badge-ota ota-{{ $booking->api_source }}" title="{{ $booking->source_name }}">
                                                {!! $otaLogo !!}
                                            </span>
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
             @click.self="closeBooking()"
             class="modal-backdrop">
            <div class="modal card p-0" @click.stop>
                <template x-if="selectedBooking">
                    <div>
                        <!-- Title: Guest name -->
                        <div class="modal-header card-header"
                             :class="'status-' + (selectedBooking?.display_status || selectedBooking?.status || '') + ' bg-' + (selectedBooking?.display_status || selectedBooking?.status || '')">
                            <h3>
                                <span x-show="selectedBooking.deleted_at" class="badge-deleted">DELETED</span>
                                <span x-text="selectedBooking.guest_name"></span>
                            </h3>
                            <button @click="closeBooking()" class="close-button text-white">
                                {!! icon('close') !!}
                            </button>
                        </div>

                        <div class="modal-content card-body">
                            <!-- Unit + Actions -->
                            <div class="fields-row">
                                <div class="unit-info">
                                    <span class="unit-name"
                                          x-text="selectedBooking.unit?.name + (selectedBooking.group ? ' + ' + (selectedBooking.group.count - 1) : '')"></span>
                                    <span class="status-badge"
                                          :class="'status-' + (selectedBooking.status || '') + ' bg-' + (selectedBooking.status || '')"
                                          x-text="selectedBooking.status_label"></span>
                                    <span class="status-badge bg-new" x-show="selectedBooking.metadata?.is_new">{{ __('booking.tag.new') }}</span>
                                    <span class="actions action-links">
                                        <a :href="selectedBooking.view_url" class="action-link" title="{{ __('app.view') }}">{!! icon('eye') !!}</a>
                                        <a :href="selectedBooking.edit_url" class="action-link" title="{{ __('app.edit') }}">{!! icon('edit') !!}</a>
                                        <template x-if="selectedBooking.source?.url">
                                            <a :href="selectedBooking.source.url" target="_blank" @click="armResync()" class="action-link" title="{{ __('booking.source.beds24') }}">{!! icon('arrow-up-right') !!}</a>
                                        </template>
                                        <template x-if="selectedBooking.origin?.url">
                                            <a :href="selectedBooking.origin.url" target="_blank" @click="armResync()" class="action-link" :title="selectedBooking.origin.channel" x-html="selectedBooking.origin.logo"></a>
                                        </template>
                                    </span>
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
                            <div x-show="selectedBooking.guests || selectedBooking.adults || selectedBooking.children" class="detail-section">
                                <div x-show="selectedBooking.guests" class="detail-line">
                                    <span class="label">{{ __('booking.field.guests') }}:</span>
                                    <span class="value" x-text="selectedBooking.guests"></span>
                                </div>
                                <div x-show="selectedBooking.adults" class="detail-line">
                                    <span class="label">{{ __('booking.field.adults') }}:</span>
                                    <span class="value" x-text="selectedBooking.adults"></span>
                                </div>
                                <div x-show="selectedBooking.children" class="detail-line">
                                    <span class="label">{{ __('booking.field.children') }}:</span>
                                    <span class="value" x-text="selectedBooking.children"></span>
                                </div>
                            </div>

                            <!-- Price / Paid / Balance -->
                            <div x-show="selectedBooking.price !== null || selectedBooking.paid !== null" class="detail-section">
                                <div x-show="selectedBooking.price !== null" class="detail-line">
                                    <span class="label">{{ __('booking.field.price') }}:</span>
                                    <span class="value" x-text="formatMoney(selectedBooking.price)"></span>
                                </div>
                                <div x-show="selectedBooking.deposit" class="detail-line">
                                    <span class="label">{{ __('booking.field.deposit') }}:</span>
                                    <span class="value" x-text="formatMoney(selectedBooking.deposit)"></span>
                                </div>
                                <div x-show="selectedBooking.paid !== null" class="detail-line">
                                    <span class="label">{{ __('booking.field.paid') }}:</span>
                                    <span class="value" x-text="formatMoney(selectedBooking.paid)"></span>
                                </div>
                                <div x-show="selectedBooking.balance !== null" class="detail-line">
                                    <span class="label">{{ __('booking.field.balance') }}:</span>
                                    <span class="value" x-text="formatMoney(selectedBooking.balance)"></span>
                                </div>
                            </div>

                            <!-- Group members -->
                            <div x-show="selectedBooking.group" class="detail-section">
                                <label>{{ __('booking.section.group') }}</label>
                                <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                                    <template x-for="member in selectedBooking.group?.members ?? []" :key="member.id">
                                        <tr :style="(member.is_current ? 'font-weight: 600;' : '') + (member.is_cancelled ? 'opacity: 0.5; text-decoration: line-through;' : '')">
                                            <td style="padding: 0.125rem 0.5rem 0.125rem 0;" x-text="member.unit_name"></td>
                                            <td style="padding: 0.125rem 0.5rem 0.125rem 0; white-space: nowrap;"
                                                x-text="formatDate(member.check_in) + ' → ' + formatDate(member.check_out)"></td>
                                            <td style="padding: 0.125rem 0; text-align: end; white-space: nowrap;"
                                                x-text="member.price !== null ? formatMoney(member.price) : '-'"></td>
                                        </tr>
                                    </template>
                                </table>
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
                                <span class="label">{{ __('booking.field.source_name') }}:</span>
                                <template x-if="selectedBooking.source?.url">
                                    <a :href="selectedBooking.source.url" target="_blank" class="link" x-text="selectedBooking.source.label"></a>
                                </template>
                                <template x-if="!selectedBooking.source?.url">
                                    <span class="value" x-text="selectedBooking.source?.label ?? '-'"></span>
                                </template>
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
            // Booking id whose source link was followed: armed on click,
            // flushed (pull + reload) when the tab regains focus or the modal
            // closes. Kept independent of selectedBooking so the modal state
            // can change without losing the pending refresh.
            pendingResyncId: null,
            resyncing: false,
            baseUrl: '{{ url('/') }}',
            locale: '{{ app()->getLocale() }}',

            init() {
                // The source usually closes its own modal on save, so the user
                // rarely closes the bokit modal by hand — pull automatically
                // when they come back to this tab.
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        this.flushResync();
                    }
                });
                window.addEventListener('focus', () => this.flushResync());
            },

            armResync() {
                this.pendingResyncId = this.selectedBooking?.id ?? null;
            },

            flushResync() {
                if (this.pendingResyncId === null || this.resyncing) {
                    return;
                }
                const id = this.pendingResyncId;
                this.pendingResyncId = null;
                this.resyncAndReload(id);
            },

            closeBooking() {
                this.selectedBooking = null;
                this.flushResync();
            },

            async resyncAndReload(id) {
                // Pull the booking's unit so a change made in the source comes
                // back into bokit, then refresh the calendar.
                this.resyncing = true;
                try {
                    await fetch(`${this.baseUrl}/booking/${id}/resync`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                            'Accept': 'application/json',
                        },
                    });
                } catch (error) {
                    console.error('Resync failed:', error);
                }
                window.location.reload();
            },

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

            formatMoney(value) {
                if (value === null || value === undefined) {
                    return '-';
                }
                return new Intl.NumberFormat(this.locale, { style: 'currency', currency: 'EUR' }).format(value);
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
</x-filament-panels::page>
