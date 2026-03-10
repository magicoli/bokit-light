<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Support\DynamicTable;
use App\Models\Booking;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingsTable
{
    private const LANG = 'booking';

    public static function configure(Table $table): Table
    {
        $config = Booking::getConfig();
        $searchable = $config['searchable'];

        // Columns not in $list_columns but searchable — shown as hidden toggleable
        $extraSearchColumns = [];
        foreach ($searchable as $field) {
            if (! in_array($field, $config['list_columns'])) {
                $extraSearchColumns[] = TextColumn::make($field)
                    ->label(__(self::LANG.".field.{$field}"))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true);
            }
        }

        return $table
            ->defaultSort('check_in', 'asc')
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->columns([
                ...DynamicTable::columns(Booking::class, self::LANG, [
                    // Notes: truncated + hidden by default
                    'notes' => TextColumn::make('notes')
                        ->label(__(self::LANG.'.field.notes'))
                        ->limit(60)
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
                ...$extraSearchColumns,
            ])
            ->filters(
                [
                    SelectFilter::make('status')
                        ->label(__(self::LANG.'.field.status'))
                        ->options(self::statusOptions()),

                    SelectFilter::make('unit')
                        ->label(__(self::LANG.'.field.unit_name'))
                        ->relationship('unit', 'name'),

                    SelectFilter::make('source_name')
                        ->label(__(self::LANG.'.field.source_name'))
                        ->options(fn (): array => Booking::query()
                            ->whereNotNull('source_name')
                            ->distinct()
                            ->pluck('source_name')
                            ->mapWithKeys(fn (string $name): array => [$name => Booking::sourceSlug($name)])
                            ->sort()
                            ->all()),
                ],
                layout: FiltersLayout::Hidden,
            )
            ->recordActions(DynamicTable::recordActions(Booking::class, self::LANG))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Status options for filters and forms.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'confirmed' => __('booking.status.confirmed'),
            'undefined' => __('booking.status.undefined'),
            'unavailable' => __('booking.status.unavailable'),
            'cancelled' => __('booking.status.cancelled'),
            'cancelled_by_owner' => __('booking.status.cancelled_by_owner'),
            'cancelled_by_guest' => __('booking.status.cancelled_by_guest'),
            'vanished' => __('booking.status.vanished'),
            'deleted' => __('booking.status.deleted'),
        ];
    }
}
