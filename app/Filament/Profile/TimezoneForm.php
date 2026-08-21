<?php

namespace App\Filament\Profile;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Joaopaulolndev\FilamentEditProfile\Concerns\HasUser;
use Joaopaulolndev\FilamentEditProfile\Livewire\BaseProfileForm;

/**
 * Registered on FilamentEditProfilePlugin via customProfileComponents() - the package's own
 * bundled fields (name/email/locale/avatar) stop short of anything app-specific, and its
 * custom_fields mechanism serializes into one JSON blob column, the wrong shape for a
 * first-class column like User.timezone (dev/project-timezone-and-tenant-settings.md). Same
 * shape as the package's own EditProfileForm.php, its actual precedent for this exact pattern.
 */
class TimezoneForm extends BaseProfileForm
{
    use HasUser;

    protected string $view = 'filament.profile.timezone-form';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static int $sort = 15;

    public function mount(): void
    {
        $this->user = $this->getUser();

        $this->form->fill(['timezone' => $this->user->getAttributeValue('timezone')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('user.field.timezone'))
                    ->aside()
                    ->description(__('user.field.timezone_description'))
                    ->schema([
                        Select::make('timezone')
                            ->label(__('user.field.timezone'))
                            ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->searchable()
                            ->native(false)
                            ->placeholder(__('app.default_value', ['value' => User::defaultTimezone()])),
                    ]),
            ])
            ->statePath('data');
    }

    public function updateTimezone(): void
    {
        $data = $this->form->getState();

        $this->user->update(['timezone' => $data['timezone'] ?? null]);

        Notification::make()->success()->title(__('filament-edit-profile::default.saved_successfully'))->send();
    }
}
