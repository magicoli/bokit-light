<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.booking'))
                    ->columns(2)
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
                                'unavailable' => 'warning',
                                'cancelled', 'cancelled_by_owner', 'cancelled_by_guest', 'deleted', 'vanished' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('check_in')
                            ->label(__('booking.field.check_in'))
                            ->date('d/m/Y'),

                        TextEntry::make('check_out')
                            ->label(__('booking.field.check_out'))
                            ->date('d/m/Y'),
                    ]),

                Section::make(__('booking.section.guests'))
                    ->columns(3)
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
                            ->money('EUR')
                            ->placeholder('-'),

                        TextEntry::make('commission')
                            ->label(__('booking.field.commission'))
                            ->money('EUR')
                            ->placeholder('-'),
                    ]),

                Section::make(__('booking.field.source_name'))
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('source_name')
                            ->label(__('booking.field.source_name'))
                            ->placeholder('-'),

                        TextEntry::make('uid')
                            ->label(__('booking.field.uid'))
                            ->placeholder('-'),

                        IconEntry::make('is_manual')
                            ->label(__('booking.field.is_manual'))
                            ->boolean(),
                    ]),

                Section::make(__('booking.section.sources'))
                    ->schema([
                        RepeatableEntry::make('sources')
                            ->label('')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('source_key')
                                    ->label(__('booking.field.source_name'))
                                    ->badge(),

                                TextEntry::make('external_id')
                                    ->label(__('booking.source.external_id'))
                                    ->copyable(),

                                IconEntry::make('is_origin')
                                    ->label(__('booking.source.origin'))
                                    ->boolean(),

                                TextEntry::make('last_seen_at')
                                    ->label(__('booking.source.last_seen'))
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->visible(fn (Booking $record): bool => $record->sources()->exists()),

                Section::make(__('booking.field.notes'))
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('booking.section.metadata'))
                    ->columns(2)
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
            ]);
    }
}
