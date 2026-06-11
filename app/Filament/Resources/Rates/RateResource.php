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
use Illuminate\Database\Eloquent\Builder;

class RateResource extends Resource
{
    protected static ?string $model = Rate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forUser();
    }
}
