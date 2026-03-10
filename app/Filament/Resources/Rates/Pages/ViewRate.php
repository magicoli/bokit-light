<?php

namespace App\Filament\Resources\Rates\Pages;

use App\Filament\Resources\Rates\RateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRate extends ViewRecord
{
    protected static string $resource = RateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
