<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Traits\GroupedBookings;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BookingsOngoing extends TableWidget
{
    use GroupedBookings;

    private const LANG = 'booking';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Booking::query())
            ->columns([
                // 'unit_name' => TextColumn::make('unit_name')
                //     ->label(__(self::LANG.'.field.unit_name'))
                //     ->getStateUsing(fn (Booking $record): ?string => $record->group_id && $record->group_units > 1
                //         ? $record->unit_name.' + '.($record->group_units - 1)
                //         : $record->unit_name),

                // 'check_in' => self::groupAwareDate('check_in', 'group_check_in', 'min'),
                // 'check_out' => self::groupAwareDate('check_out', 'group_check_out', 'max'),

                // Computed accessor (no SQL column behind it): searching and
                // sorting are not available on it.
                'title' => TextColumn::make('title')
                    ->label(__(self::LANG.'.field.guest_name'))
                    ->wrap(),

                // 'guests' => self::groupAwareSum('guests', 'group_guests'),
                // 'adults' => self::groupAwareSum('adults', 'group_adults'),
                // 'children' => self::groupAwareSum('children', 'group_children'),

                'price' => TextColumn::make('price')
                    ->label(__(self::LANG.'.field.price'))
                    ->money('EUR', locale: fn (): string => app()->getLocale())
                    ->alignEnd()
                    ->getStateUsing(fn (Booking $record) => $record->group_id
                        ? $record->group_price
                        : $record->price)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw('coalesce((select sum(m.price) from bookings m where '.self::activeMember().'), price) '.$direction)),

                // ...$extraSearchColumns,
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
