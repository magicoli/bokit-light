<?php

namespace App\Filament\Pages;

use App\Support\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/**
 * App-wide settings, admin-only. Starts with just the display timezone (porting
 * AdminController::settingsFields()'s legacy /legacy-admin/settings equivalent onto a real
 * Filament page) - more settings land here later, not built out ahead of need.
 *
 * Storage stays on the existing Options/options() JSON-file cascade
 * (dev/project-timezone-and-tenant-settings.md) - rewriting it onto native storage is optional
 * and deferred, not part of this page.
 */
class GeneralSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getNavigationGroup(): string
    {
        return __('app.admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.admin_settings');
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'timezone' => Options::get('timezone'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('timezone')
                ->label(__('app.display_timezone'))
                ->helperText(__('app.display_timezone_help'))
                ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()
                ->native(false)
                ->placeholder(__('app.default_value', ['value' => config('app.timezone')])),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function getTitle(): string
    {
        return __('app.admin_settings');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('app.save_settings'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Options::set('timezone', $data['timezone'] ?? null);

        Notification::make()->success()->title(__('app.settings_saved'))->send();
    }
}
