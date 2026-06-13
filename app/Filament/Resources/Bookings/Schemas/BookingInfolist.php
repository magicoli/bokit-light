<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.booking'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        TextEntry::make('property.name')
                            ->label(__('booking.field.property_id')),

                        TextEntry::make('unit.name')
                            ->label(__('booking.field.unit_name')),

                        TextEntry::make('guest_name')
                            ->label(__('booking.field.guest_name')),

                        TextEntry::make('status')
                            ->label(__('booking.field.status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'confirmed' => 'success',
                                'undefined' => 'gray',
                                'option' => 'warning',
                                'quote' => 'info',
                                'blocked', 'unavailable' => 'warning',
                                'cancelled', 'deleted', 'vanished' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('check_in')
                            ->label(__('booking.field.check_in'))
                            ->date('d/m/Y'),

                        TextEntry::make('check_out')
                            ->label(__('booking.field.check_out'))
                            ->date('d/m/Y'),

                        // Real origin channel, linking to the OTA's own
                        // reservation page when available (airbnb, booking.com).
                        TextEntry::make('source_name')
                            ->label(__('booking.field.source_name'))
                            ->placeholder('-')
                            ->url(fn (Booking $record): ?string => $record->originUrl())
                            ->openUrlInNewTab()
                            ->color(fn (Booking $record): string => $record->originUrl() ? 'primary' : 'gray'),
                    ]),

                Section::make(__('booking.section.guests'))
                    ->columns(['default' => 2, 'sm' => 3])
                    ->schema([
                        TextEntry::make('adults')
                            ->label(__('booking.field.adults'))
                            ->numeric()
                            ->placeholder('-'),

                        TextEntry::make('children')
                            ->label(__('booking.field.children'))
                            ->numeric()
                            ->placeholder('-'),

                        TextEntry::make('guests')
                            ->label(__('booking.field.guests'))
                            ->numeric()
                            ->placeholder('-'),

                        TextEntry::make('price')
                            ->label(__('booking.field.price'))
                            ->money('EUR', locale: fn (): string => app()->getLocale())
                            ->placeholder('-'),

                        TextEntry::make('deposit')
                            ->label(__('booking.field.deposit'))
                            ->state(fn (Booking $record): ?float => self::floatMeta($record, 'deposit'))
                            ->money('EUR', locale: fn (): string => app()->getLocale())
                            ->placeholder('-'),

                        TextEntry::make('paid')
                            ->label(__('booking.field.paid'))
                            ->state(fn (Booking $record): ?float => self::paidAmount($record))
                            ->money('EUR', locale: fn (): string => app()->getLocale())
                            ->placeholder('-'),

                        TextEntry::make('balance')
                            ->label(__('booking.field.balance'))
                            ->state(function (Booking $record): ?float {
                                $price = $record->getRawOriginal('price');

                                return $price !== null
                                    ? round((float) $price - (self::paidAmount($record) ?? 0), 2)
                                    : null;
                            })
                            ->money('EUR', locale: fn (): string => app()->getLocale())
                            ->placeholder('-'),

                        TextEntry::make('commission')
                            ->label(__('booking.field.commission'))
                            ->money('EUR', locale: fn (): string => app()->getLocale())
                            ->placeholder('-'),
                    ]),

                Section::make(__('booking.field.notes'))
                    ->collapsible()
                    ->schema([
                        TextEntry::make('notes')
                            ->hiddenLabel()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('booking.section.group'))
                    ->schema([
                        ViewEntry::make('group_members')
                            ->hiddenLabel()
                            ->view('filament.bookings.group-table'),
                    ])->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Booking $record): bool => $record->group_id !== null),

                Section::make(__('booking.section.invoice'))
                    ->schema([
                        ViewEntry::make('invoice_lines')
                            ->hiddenLabel()
                            ->view('filament.bookings.invoice-table'),
                    ])
                    ->collapsible()
                    ->visible(fn (Booking $record): bool => ! empty($record->getMetadata('invoice_lines'))),

                Section::make(__('booking.section.sources'))
                    ->schema([
                        ViewEntry::make('sources')
                            ->hiddenLabel()
                            ->view('filament.bookings.sources-table'),
                    ])
                    ->collapsed()
                    ->visible(fn (Booking $record): bool => $record->sources()->exists()),

                Section::make(__('booking.section.metadata'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('booking.field.created_at'))
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('updated_at')
                            ->label(__('booking.field.updated_at'))
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('deleted_at')
                            ->label(__('booking.field.deleted_at'))
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn (Booking $record): bool => $record->trashed()),
                    ]),
            ])->columns(3);
    }

    private static function floatMeta(Booking $record, string $key): ?float
    {
        $value = $record->getMetadata($key);

        return $value !== null && $value !== '' ? (float) $value : null;
    }

    /**
     * Amount actually received: Beds24 invoice payment lines, or the
     * 'paid' metadata used by other sources (hbook).
     */
    private static function paidAmount(Booking $record): ?float
    {
        return self::floatMeta($record, 'invoice_payment_total')
            ?? self::floatMeta($record, 'paid');
    }
}
