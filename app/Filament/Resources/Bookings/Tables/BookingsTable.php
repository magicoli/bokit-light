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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
            // Secondary sort on group_id keeps members of a group reservation
            // adjacent when they share the same check-in date.
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('check_in')->orderBy('group_id'))
            ->groups([
                Group::make('group_id')
                    ->label(__(self::LANG.'.field.group'))
                    ->getTitleFromRecordUsing(fn (Booking $record): string => $record->group_id
                        ? $record->guest_name.' — '.$record->check_in->format('d/m/Y')
                        : __(self::LANG.'.group.none'))
                    ->collapsible(),
            ])
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
            ])
            ->recordClasses(fn (Model $record): string => match (true) {
                // Group summary row: no unit, holds total price/guests for the group.
                $record->unit_id === null => 'booking-group-summary',
                // Group member row: individual unit within a group, no price/guests.
                ($record->metadata['is_group_member'] ?? false) => 'booking-group-member',
                default => '',
            });
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
