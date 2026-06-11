<?php

namespace App\Filament\Widgets;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ongoing stays, ordered by departure.
 */
class BookingsOngoing extends BookingListWidget
{
    protected static ?int $sort = 1;

    protected function tableHeading(): string
    {
        return __('booking.widget.ongoing');
    }

    protected function scopeList(Builder $query): Builder
    {
        return $query
            ->where('status', 'confirmed')
            ->whereDate('check_in', '<=', today())
            ->whereDate('check_out', '>=', today())
            ->orderBy('check_out');
    }
}
