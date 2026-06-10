<?php

namespace Modules\Beds24;

use App\Filament\Resources\Properties\Schemas\PropertyForm;
use App\Services\SyncRegistry;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\ServiceProvider;
use Modules\Beds24\Commands\Beds24SyncCommand;
use Modules\Beds24\Services\Beds24SyncHandler;

class Beds24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/beds24.php', 'beds24');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'beds24');

        $this->extendPropertyForm();
        $this->registerSyncHandler();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Beds24SyncCommand::class,
            ]);
        }
    }

    /**
     * Register the Beds24SyncHandler into the core SyncRegistry so that
     * bokit:sync picks it up without knowing about this module.
     */
    private function registerSyncHandler(): void
    {
        /** @var SyncRegistry $registry */
        $registry = $this->app->make(SyncRegistry::class);
        $registry->register(new Beds24SyncHandler);
    }

    /**
     * Inject Beds24 API settings section into the property edit form.
     */
    private function extendPropertyForm(): void
    {
        PropertyForm::extend(function (array $components): array {
            $components[] = Section::make(__('beds24::property.section.beds24'))
                ->description(__('beds24::property.section.beds24_description'))
                ->schema([
                    TextInput::make('options.beds24_api_key')
                        ->label(__('beds24::property.field.beds24_api_key'))
                        ->helperText(__('beds24::property.field.beds24_api_key_help'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('options.beds24_prop_key')
                        ->label(__('beds24::property.field.beds24_prop_key'))
                        ->helperText(__('beds24::property.field.beds24_prop_key_help'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                ]);

            return $components;
        });
    }
}
