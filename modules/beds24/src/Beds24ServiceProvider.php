<?php

namespace Modules\Beds24;

use App\Filament\Resources\Properties\Schemas\PropertyForm;
use App\Services\SyncRegistry;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Modules\Beds24\Services\Beds24Connector;
use Modules\Beds24\Services\Beds24V2ApiService;

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
        $this->registerSourceConnector();

    }

    /**
     * Register the Beds24Connector into the core SyncRegistry so that
     * bokit:sync picks it up without knowing about this module.
     */
    private function registerSourceConnector(): void
    {
        /** @var SyncRegistry $registry */
        $registry = $this->app->make(SyncRegistry::class);
        $registry->register(new Beds24Connector);

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

                    // API v2 (write access): pasting an invite code exchanges
                    // it immediately for a permanent refresh token, stored
                    // with the property options when the form is saved.
                    Hidden::make('options.beds24_refresh_token'),

                    TextInput::make('beds24_invite_code')
                        ->label(__('beds24::property.field.beds24_invite_code'))
                        ->helperText(new HtmlString(__('beds24::property.field.beds24_invite_code_help', [
                            'url' => 'https://beds24.com/control3.php?pagetype=apiv2',
                        ])))
                        ->dehydrated(false)
                        ->live(onBlur: true)
                        ->hint(fn (SchemaGet $get): string => filled($get('options.beds24_refresh_token'))
                            ? __('beds24::property.field.beds24_v2_connected')
                            : __('beds24::property.field.beds24_v2_not_connected'))
                        ->hintColor(fn (SchemaGet $get): string => filled($get('options.beds24_refresh_token')) ? 'success' : 'gray')
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            try {
                                $token = Beds24V2ApiService::exchangeInviteCode(trim($state));
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title(__('beds24::property.notification.invite_code_failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $set('options.beds24_refresh_token', $token);
                            $set('beds24_invite_code', null);

                            Notification::make()
                                ->title(__('beds24::property.notification.invite_code_exchanged'))
                                ->success()
                                ->send();
                        }),
                ]);

            return $components;
        });
    }
}
