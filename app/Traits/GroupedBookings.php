<?php

namespace App\Traits;

use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

trait GroupedBookings
{
    // use AdminResourceTrait;
    // use SoftDeletes;
    // use TimezoneTrait;

    private const LANG = 'booking';

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
     * One row per group reservation: ungrouped bookings pass through, each
     * group is represented by a single member (non-cancelled first, master
     * preferred, oldest as fallback).
     */
    private static function groupRepresentatives(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('group_id')
                ->orWhereRaw('bookings.id = (select m.id from bookings m where m.group_id = bookings.group_id and m.deleted_at is null order by (m.status not in ('.self::cancelledList().")) desc, (m.uid = 'beds24-' || m.group_id) desc, m.id limit 1)");
        });
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
}
