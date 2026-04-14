<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasRelationManagers;

class EditUnit extends EditRecord
{
    use HasRelationManagers;

    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Use full width for the edit page
     */
    protected function getMaxContentWidth(): string
    {
        return 'full';
    }
}
