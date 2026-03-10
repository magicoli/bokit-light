<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\DynamicTable;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class UsersTable
{
    private const LANG = 'user';

    public static function configure(Table $table): Table
    {
        return $table
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->columns(DynamicTable::columns(User::class, self::LANG))
            ->filters([])
            ->recordActions(DynamicTable::recordActions(User::class, self::LANG))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
