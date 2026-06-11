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

    protected function extraColumns(): array
    {
        return [$this->amountsColumn()];
    }

    protected function listParameters(): array
    {
        return ['filters' => ['status' => ['value' => 'confirmed'], 'period' => ['value' => 'ongoing']], 'sort' => 'check_out:asc'];
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
