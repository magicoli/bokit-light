<?php

namespace App\Filament\Resources\Rates\Tables;

use App\Filament\Support\DynamicTable;
use App\Models\Rate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RatesTable
{
    private const LANG = 'rates';

    public static function configure(Table $table): Table
    {
        return $table
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->columns(DynamicTable::columns(Rate::class, self::LANG))
            ->filters([
                SelectFilter::make('property')
                    ->label(__(self::LANG.'.field.property_id'))
                    ->relationship('property', 'name'),

                SelectFilter::make('is_active')
                    ->label(__(self::LANG.'.field.is_active'))
                    ->options([
                        '1' => __('app.yes'),
                        '0' => __('app.no'),
                    ]),

                SelectFilter::make('priority')
                    ->label(__(self::LANG.'.field.priority'))
                    ->options([
                        'high' => __('rate.priority_high'),
                        'normal' => __('rate.priority_normal'),
                        'low' => __('rate.priority_low'),
                    ]),
            ])
            ->recordActions(DynamicTable::recordActions(Rate::class, self::LANG))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
