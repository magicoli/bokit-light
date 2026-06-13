<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Concerns\PushesBookingOnSave;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    use PushesBookingOnSave;

    protected static string $resource = BookingResource::class;

    /**
     * A booking created in the panel originates in bokit: mark it manual so
     * the sync engine treats bokit as its owner and never lets a source
     * overwrite it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_manual'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->pushBookingOnSave();
    }
}
