<?php

namespace App\Filament\Resources\Rates;

use App\Filament\Resources\Rates\Pages\CreateRate;
use App\Filament\Resources\Rates\Pages\EditRate;
use App\Filament\Resources\Rates\Pages\ListRates;
use App\Filament\Resources\Rates\Pages\ViewRate;
use App\Filament\Resources\Rates\Schemas\RateForm;
use App\Filament\Resources\Rates\Schemas\RateInfolist;
use App\Filament\Resources\Rates\Tables\RatesTable;
use App\Models\Rate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RateResource extends Resource
{
    protected static ?string $model = Rate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('rate.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('rate.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return RateForm::configure($schema)->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRates::route('/'),
            'create' => CreateRate::route('/create'),
            'view' => ViewRate::route('/{record}'),
            'edit' => EditRate::route('/{record}/edit'),
        ];
    }

    // No more getEloquentQuery()->forUser() override: Property is the App panel's own tenant now
    // (dev/project-app-panel-tenancy.md) — Filament scopes every query to the current tenant
    // automatically via Rate::property() (already a plain belongsTo).
}
