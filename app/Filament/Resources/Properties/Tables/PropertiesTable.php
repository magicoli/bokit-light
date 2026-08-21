<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Filament\Support\DynamicTable;
use App\Models\Property;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class PropertiesTable
{
    private const LANG = 'property';

    public static function configure(Table $table): Table
    {
        return $table
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->columns(DynamicTable::columns(Property::class, self::LANG))
            ->filters([])
            ->recordActions(DynamicTable::recordActions(Property::class, self::LANG))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('property.empty_state'));
    }
}
