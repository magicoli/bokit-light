<?php

namespace App\Filament\Support;

/**
 * Skip the list page when the user only has access to a single record:
 * a property owner with one property (or one unit) lands straight on its
 * page. Admins and managers keep the list and its Create button.
 */
trait RedirectsToSingleRecord
{
    public function mount(): void
    {
        parent::mount();

        $user = auth()->user();

        if (! $user || $user->isAdmin() || $user->hasRole('manager')) {
            return;
        }

        $records = static::getResource()::getEloquentQuery()->limit(2)->get();

        if ($records->count() === 1) {
            $this->redirect(static::getResource()::getUrl('view', ['record' => $records->first()]));
        }
    }
}
