<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.unit'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('unit.field.name')),
                        TextEntry::make('property.name')->label(__('unit.field.property_id')),
                        TextEntry::make('unit_type')->label(__('unit.field.unit_type')),
                        TextEntry::make('bedrooms')->label(__('unit.field.bedrooms')),
                        TextEntry::make('max_guests')->label(__('unit.field.max_guests')),
                        IconEntry::make('is_active')->label(__('unit.field.is_active'))->boolean(),
                    ]),

                Section::make(__('unit.field.description'))
                    ->schema([
                        TextEntry::make('description')->label(__('unit.field.description')),
                    ]),
            ]);
    }
}
