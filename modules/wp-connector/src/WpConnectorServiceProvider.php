<?php

namespace Modules\WpConnector;

use App\Filament\Resources\Properties\Schemas\PropertyForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\ServiceProvider;

class WpConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'wp-connector');

        $this->extendPropertyForm();
    }

    /**
     * Inject a WordPress connection tab into the property edit form.
     */
    private function extendPropertyForm(): void
    {
        PropertyForm::extend(function (array $tabs): array {
            $tabs[] = Tab::make(__('wp-connector::property.section.wordpress'))
                ->schema([
                    TextInput::make('options.wp_url')
                        ->label(__('wp-connector::property.field.wp_url'))
                        ->helperText(__('wp-connector::property.field.wp_url_help'))
                        ->url()
                        ->maxLength(255),
                    TextInput::make('options.wp_user')
                        ->label(__('wp-connector::property.field.wp_user'))
                        ->helperText(__('wp-connector::property.field.wp_user_help'))
                        ->maxLength(100),
                    TextInput::make('options.wp_app_password')
                        ->label(__('wp-connector::property.field.wp_app_password'))
                        ->helperText(__('wp-connector::property.field.wp_app_password_help'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                ]);

            return $tabs;
        });
    }
}
