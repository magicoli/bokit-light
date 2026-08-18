<?php

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
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
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;
use Magicoli\ExtraNavigationItems\NavigationItemsPlugin;

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
                // Vite::asset(), not resource_path(): Filament publishes a local path verbatim,
                // which would ship @import and @apply straight to the browser. Everything under
                // resources/ is built, and the panels are served what the build produced.
                Css::make('glass-stylesheet', Vite::asset('resources/css/glass.css')),
                Css::make('panels-stylesheet', Vite::asset('resources/css/panels.css')),
                Css::make('legacy-stylesheet', Vite::asset('resources/css/legacy.css')),
                Js::make('panels-script', Vite::asset('resources/js/panels.js')),
            ])
            // ->breadcrumbs(false)
            ->sidebarCollapsibleOnDesktop()
            ->plugins([
                // Cross-panel shortcuts (Calendar + the legacy admin), rendered next to the user
                // menu on every panel that shares this config — realising the "Calendar + Admin
                // links in the topbar, shared with the frontend" that each panel used to carry as
                // its own duplicated navigationItems(). Each item hides itself when it does not
                // apply. See NavigationItemsPlugin (magicoli/extra-navigation-items).
                NavigationItemsPlugin::make()->items(self::sharedShortcuts()),
                // The credit line at the foot of every panel.
                NavigationItemsPlugin::make()->renderHook(PanelsRenderHook::FOOTER)->items(self::footerItems()),
            ])
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

    /**
     * The shortcuts shown on every shared panel, next to the user menu — the panel switchers that
     * used to be repeated, and drift, in each panel's own navigationItems(). Defined once here;
     * every item carries its own visibility.
     *
     * @return array<int, NavigationItem>
     */
    protected static function sharedShortcuts(): array
    {
        return [
            NavigationItem::make('shared-calendar')
                ->label(fn(): string => __('app.calendar'))
                ->icon('heroicon-o-calendar-date-range')
                ->url(fn(): string => route('calendar'))
                ->visible(fn(): bool => auth()->check()),
            NavigationItem::make('shared-admin')
                ->label(fn(): string => __('app.obsolete'))
                ->icon('ri-dashboard-line')
                ->url(fn(): string => route('admin.dashboard'))
                ->visible(fn(): bool => (bool) auth()->user()?->isAdmin()),
        ];
    }

    /**
     * The footer credit line: the application's name and version, label only. Rendered at the
     * FOOTER hook, which has no navigation chrome to borrow, so it comes out as plain text.
     *
     * @return array<int, NavigationItem>
     */
    protected static function footerItems(): array
    {
        return [
            NavigationItem::make('credit')->label(fn(): string => config('app.name') . ' ' . config('app.version')),
        ];
    }
}
