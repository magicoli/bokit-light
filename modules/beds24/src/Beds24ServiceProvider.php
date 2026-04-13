<?php

namespace Modules\Beds24;

use App\Filament\Resources\Properties\Schemas\PropertyForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\ServiceProvider;

class Beds24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/beds24.php', 'beds24');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'beds24');

        $this->extendPropertyForm();
    }

    /**
     * Inject Beds24 settings section into the property edit form.
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
