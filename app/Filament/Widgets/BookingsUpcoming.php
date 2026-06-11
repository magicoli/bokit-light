<?php

namespace App\Filament\Widgets;

use Illuminate\Database\Eloquent\Builder;

/**
 * Upcoming stays, ordered by arrival.
 */
class BookingsUpcoming extends BookingListWidget
{
    protected static ?int $sort = 2;

    protected function tableHeading(): string
    {
        return __('booking.widget.upcoming');
    }

    protected function extraColumns(): array
    {
        return [$this->amountsColumn()];
    }

    protected function scopeList(Builder $query): Builder
    {
        return $query
            ->where('status', 'confirmed')
            ->whereDate('check_in', '>', today())
            ->orderBy('check_in');
    }
}
