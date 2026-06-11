<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Support\DynamicTable;
use App\Models\Booking;
use App\Models\Unit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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
            ->defaultSort('check_in', 'desc')
            // Group reservations show a single row: the group master (or the
            // oldest non-cancelled member as fallback) represents the group;
            // aggregate columns are selected as subqueries so display and
            // sorting use the group totals. Cancelled members hold nothing
            // and count for nothing in the aggregates. Past or ongoing
            // 'blocked' rows are platform artifacts and stay hidden.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where(function (Builder $q): void {
                    $q->where('status', '!=', 'blocked')
                        ->orWhere('check_in', '>', now()->format('Y-m-d'));
                })
                ->where(function (Builder $q): void {
                    $q->whereNull('group_id')
                        ->orWhereRaw('bookings.id = (select m.id from bookings m where m.group_id = bookings.group_id and m.deleted_at is null order by (m.status not in ('.self::cancelledList().")) desc, (m.uid = 'beds24-' || m.group_id) desc, m.id limit 1)");
                })
                ->select('bookings.*')
                ->selectRaw('(select count(*) from bookings m where '.self::activeMember().') as group_units')
                ->selectRaw('(select min(m.check_in) from bookings m where '.self::activeMember().') as group_check_in')
                ->selectRaw('(select max(m.check_out) from bookings m where '.self::activeMember().') as group_check_out')
                ->selectRaw('(select sum(m.price) from bookings m where '.self::activeMember().') as group_price')
                ->selectRaw('(select sum(m.guests) from bookings m where '.self::activeMember().') as group_guests')
                ->selectRaw('(select sum(m.adults) from bookings m where '.self::activeMember().') as group_adults')
                ->selectRaw('(select sum(m.children) from bookings m where '.self::activeMember().') as group_children'))
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->columns([
                ...DynamicTable::columns(Booking::class, self::LANG, [
                    'unit_name' => TextColumn::make('unit_name')
                        ->label(__(self::LANG.'.field.unit_name'))
                        ->getStateUsing(fn (Booking $record): ?string => $record->group_id && $record->group_units > 1
                            ? $record->unit_name.' + '.($record->group_units - 1)
                            : $record->unit_name),

                    'check_in' => self::groupAwareDate('check_in', 'group_check_in', 'min'),
                    'check_out' => self::groupAwareDate('check_out', 'group_check_out', 'max'),

                    // Wrap long guest names so the column flexes instead of
                    // pushing the other columns off-screen.
                    'guest_name' => TextColumn::make('guest_name')
                        ->label(__(self::LANG.'.field.guest_name'))
                        ->searchable()
                        ->sortable()
                        ->wrap(),

                    'guests' => self::groupAwareSum('guests', 'group_guests'),
                    'adults' => self::groupAwareSum('adults', 'group_adults'),
                    'children' => self::groupAwareSum('children', 'group_children'),

                    'price' => TextColumn::make('price')
                        ->label(__(self::LANG.'.field.price'))
                        ->money('EUR', locale: fn (): string => app()->getLocale())
                        ->alignEnd()
                        ->getStateUsing(fn (Booking $record) => $record->group_id
                            ? $record->group_price
                            : $record->price)
                        ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                            ->orderByRaw('coalesce((select sum(m.price) from bookings m where '.self::activeMember().'), price) '.$direction)),

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
                    // Default view only shows effective bookings (confirmed,
                    // requests, …) — untoggle to see cancelled/deleted/vanished.
                    // Stands down when a status is explicitly selected, so
                    // picking "Cancelled" in the status filter still works.
                    Filter::make('effective')
                        ->label(__(self::LANG.'.filter.effective_only'))
                        ->toggle()
                        ->default()
                        ->query(fn (Builder $query, HasTable $livewire): Builder => ($livewire->tableFilters['status']['value'] ?? null)
                            ? $query
                            : $query->whereNotIn('status', Booking::CANCELLED_STATUSES)),

                    SelectFilter::make('status')
                        ->label(__(self::LANG.'.field.status'))
                        ->options(self::statusOptions()),

                    SelectFilter::make('unit')
                        ->label(__(self::LANG.'.field.unit_name'))
                        ->options(fn (): array => Unit::forUser()->orderBy('name')->pluck('name', 'id')->all())
                        ->query(fn (Builder $query, array $data): Builder => $query
                            ->when($data['value'] ?? null, fn (Builder $q, $unitId): Builder => $q
                                ->where(fn (Builder $qq) => $qq
                                    ->where('unit_id', $unitId)
                                    ->orWhereRaw('exists (select 1 from bookings m where m.group_id = bookings.group_id and m.unit_id = ? and m.deleted_at is null)', [$unitId])))),

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
     * SQL fragment selecting the members of the current row's group that
     * still count: not soft-deleted and not in the cancelled family.
     */
    private static function activeMember(): string
    {
        return 'm.group_id = bookings.group_id and m.deleted_at is null and m.status not in ('.self::cancelledList().')';
    }

    private static function cancelledList(): string
    {
        return "'".implode("','", Booking::CANCELLED_STATUSES)."'";
    }

    /**
     * Date column showing the group's overall date for group rows.
     * Sorting uses the aggregate so group rows sort by their real span.
     */
    private static function groupAwareDate(string $field, string $aggregateAlias, string $aggregateFn): TextColumn
    {
        return TextColumn::make($field)
            ->label(__(self::LANG.".field.{$field}"))
            ->date('d/m/Y')
            ->getStateUsing(fn (Booking $record) => $record->group_id
                ? ($record->{$aggregateAlias} ?? $record->{$field})
                : $record->{$field})
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                ->orderByRaw("coalesce((select {$aggregateFn}(m.{$field}) from bookings m where ".self::activeMember()."), {$field}) {$direction}"));
    }

    /**
     * Numeric column showing the group total for group rows.
     */
    private static function groupAwareSum(string $field, string $aggregateAlias): TextColumn
    {
        return TextColumn::make($field)
            ->label(__(self::LANG.".field.{$field}"))
            ->numeric()
            ->getStateUsing(fn (Booking $record) => $record->group_id
                ? $record->{$aggregateAlias}
                : $record->{$field})
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                ->orderByRaw('coalesce((select sum(m.'.$field.') from bookings m where '.self::activeMember()."), {$field}) {$direction}"));
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
            'option' => __('booking.status.option'),
            'quote' => __('booking.status.quote'),
            'blocked' => __('booking.status.blocked'),
            'cancelled' => __('booking.status.cancelled'),
            'vanished' => __('booking.status.vanished'),
            'deleted' => __('booking.status.deleted'),
            'undefined' => __('booking.status.undefined'),
        ];
    }
}
