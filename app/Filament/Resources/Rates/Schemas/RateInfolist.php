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
                        TextEntry::make('name')->label(__('rates.field.name')),
                        TextEntry::make('property.name')->label(__('rates.field.property_id')),
                        TextEntry::make('unit.name')->label(__('rates.field.unit_id')),
                        TextEntry::make('unit_type')->label(__('rates.field.unit_type')),
                        TextEntry::make('base')->label(__('rates.field.base'))->money('EUR'),
                        TextEntry::make('calculation_formula')->label(__('rates.field.calculation_formula')),
                        TextEntry::make('priority')->label(__('rates.field.priority')),
                        IconEntry::make('is_active')->label(__('rates.field.is_active'))->boolean(),
                    ]),

                Section::make(__('rates.section_dates'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('booking_from')->label(__('rates.field.booking_from'))->date('d/m/Y'),
                        TextEntry::make('booking_to')->label(__('rates.field.booking_to'))->date('d/m/Y'),
                        TextEntry::make('stay_from')->label(__('rates.field.stay_from'))->date('d/m/Y'),
                        TextEntry::make('stay_to')->label(__('rates.field.stay_to'))->date('d/m/Y'),
                    ]),
            ]);
    }
}
