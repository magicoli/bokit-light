<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Traits\GroupedBookings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base for the dashboard booking mini-lists: a fixed-sort, ten-row list of
 * booking titles linking to the booking page. One row per group
 * reservation; data is scoped to the user's properties.
 */
abstract class BookingListWidget extends TableWidget
{
    use GroupedBookings;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    abstract protected function tableHeading(): string;

    /**
     * Status/date filter and fixed sort order of the mini-list.
     */
    abstract protected function scopeList(Builder $query): Builder;

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->tableHeading())
            ->query(fn (): Builder => $this->scopeList(
                self::groupRepresentatives(Booking::query()->forUser()->with(['unit', 'property']))
            )->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('')
                    ->wrap(),
            ])
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]));
    }
}
