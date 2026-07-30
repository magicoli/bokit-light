<?php

namespace App\Filament\Concerns;

use BezhanSalleh\LanguageSwitch\Enums\Placement;
use BezhanSalleh\LanguageSwitch\Enums\TriggerStyle;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

trait HasSharedPanelConfig
{
    /**
     * Apply common configurations to a Filament panel.
     */
    public function applyCommonConfig(Panel $panel): Panel
    {
        return $panel
            // Shared Theme & Styling
            ->homeUrl('/')
            // ->login()
            ->brandLogo('/images/logo.png')
            ->brandLogoHeight(fn() => request()->is('login', '*/login') ? '128px' : '48px')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Teal,
            ])
            ->assets([
                Css::make('panels-stylesheet', resource_path('css/panels.css')),
                Js::make('panels-script', resource_path('js/panels.js')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // ])
                // ->authMiddleware([
                //     Authenticate::class,
            ]);
    }
}
