<?php

namespace App\Filament\Widgets;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pending options (blocking requests), most recently updated first.
 */
class BookingsOptions extends BookingListWidget
{
    protected static ?int $sort = 3;

    protected function tableHeading(): string
    {
        return __('booking.widget.options');
    }

    protected function extraColumns(): array
    {
        return [$this->updatedAtColumn()];
    }

    protected function scopeList(Builder $query): Builder
    {
        return $query
            ->where('status', 'option')
            ->whereDate('check_out', '>=', today())
            ->orderByDesc('updated_at');
    }
}
