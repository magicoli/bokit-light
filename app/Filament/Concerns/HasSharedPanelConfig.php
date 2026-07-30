<?php

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
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
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;

trait HasSharedPanelConfig
{
    public string $panel_id;
    public string $panel_path;

    /**
     * Apply common configurations to a Filament panel.
     */
    public function applyCommonConfig(Panel $panel, string $id = null, string $path = null): Panel
    {
        return $panel
            // Shared Theme & Styling
            ->homeUrl('/')
            ->login()
            ->brandLogo('/images/logo.png')
            ->brandLogoHeight(fn() => request()->is('login', '*/login') ? '128px' : '48px')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Teal,
            ])
            ->assets([
                Css::make('panels-stylesheet', resource_path('css/panels.css')),
                Css::make('legacy-stylesheet', resource_path('css/legacy.css')),
                Js::make('panels-script', resource_path('js/panels.js')),
            ])
            // ->breadcrumbs(false)
            ->sidebarCollapsibleOnDesktop()
            // ->sidebarFullyCollapsibleOnDesktop()
            // ->userMenuItems([
            //     'profile' => MenuItem::make()
            //         ->label(fn() => auth()->user()?->name ?? __('Profile'))
            //         ->url(fn(): string => EditProfilePage::getUrl())
            //         ->icon('heroicon-m-user-circle')
            //         ->visible(fn(): bool => auth()->check()),
            // ])
            // ->widgets([]) // $id is needed for that
            ->unsavedChangesAlerts()
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
