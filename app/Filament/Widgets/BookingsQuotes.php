<?php

namespace App\Filament\Widgets;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pending quotes (non-blocking inquiries), most recently updated first.
 */
class BookingsQuotes extends BookingListWidget
{
    protected static ?int $sort = 4;

    protected function tableHeading(): string
    {
        return __('booking.widget.quotes');
    }

    protected function extraColumns(): array
    {
        return [$this->updatedAtColumn()];
    }

    protected function listParameters(): array
    {
        return ['filters' => ['status' => ['value' => 'quote']], 'sort' => 'updated_at:desc'];
    }

    protected function scopeList(Builder $query): Builder
    {
        return $query
            ->where('status', 'quote')
            ->whereDate('check_out', '>=', today())
            ->orderByDesc('updated_at');
    }
}
