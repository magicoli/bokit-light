<?php

namespace App\Filament\Resources\Units\Tables;

use App\Filament\Support\DynamicTable;
use App\Models\Unit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitsTable
{
    private const LANG = 'unit';

    public static function configure(Table $table): Table
    {
        return $table
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->columns(DynamicTable::columns(Unit::class, self::LANG))
            ->filters([
                SelectFilter::make('property')
                    ->label(__('unit.field.property_id'))
                    ->relationship('property', 'name'),

                SelectFilter::make('is_active')
                    ->label(__('unit.field.is_active'))
                    ->options([
                        '1' => __('app.yes'),
                        '0' => __('app.no'),
                    ]),
            ])
            ->recordActions(DynamicTable::recordActions(Unit::class, self::LANG))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('unit.empty_state'));
    }
}
