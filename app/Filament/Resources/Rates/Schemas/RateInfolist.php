<?php

namespace App\Filament\Resources\Rates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.rate'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('rate.field.name')),
                        TextEntry::make('property.name')->label(__('rate.field.property_id')),
                        TextEntry::make('unit.name')->label(__('rate.field.unit_id')),
                        TextEntry::make('unit_type')->label(__('rate.field.unit_type')),
                        TextEntry::make('base')->label(__('rate.field.base'))->money('EUR', locale: fn (): string => app()->getLocale()),
                        TextEntry::make('calculation_formula')->label(__('rate.field.calculation_formula')),
                        TextEntry::make('priority')->label(__('rate.field.priority')),
                        IconEntry::make('is_active')->label(__('rate.field.is_active'))->boolean(),
                    ]),

                Section::make(__('rate.section_dates'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('booking_from')->label(__('rate.field.booking_from'))->date('d/m/Y'),
                        TextEntry::make('booking_to')->label(__('rate.field.booking_to'))->date('d/m/Y'),
                        TextEntry::make('stay_from')->label(__('rate.field.stay_from'))->date('d/m/Y'),
                        TextEntry::make('stay_to')->label(__('rate.field.stay_to'))->date('d/m/Y'),
                    ]),
            ]);
    }
}
