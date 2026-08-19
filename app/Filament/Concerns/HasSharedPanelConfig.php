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
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
use Magicoli\TwoWayTicket\ReportIssuePlugin;
use Magicoli\TwoWayTicket\TicketsPlugin;

trait HasSharedPanelConfig
{
    public string $panel_id;
    public string $panel_path;

    /**
     * Apply common configurations to a Filament panel.
     */
    public function applyCommonConfig(Panel $panel, string $id, ?string $path = null): Panel
    {
        $path = $path === null ? $id : $path;

        return $panel
            ->id($id)
            ->path($path)
            ->homeUrl('/')
            // No ->login() here: login is the main panel's alone — one /login for the whole app.
            // Other panels send their guests there through redirectGuestsTo (bootstrap/app.php).
            ->brandLogo('/images/logo.png')
            ->brandLogoHeight(fn() => request()->is('login', '*/login') ? '128px' : '48px')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Teal,
            ])
            ->assets($this->panelViteAssets())
            // ->breadcrumbs(false)
            ->sidebarCollapsibleOnDesktop()
            ->plugins([
                ReportIssuePlugin::make(),
                // The edit-profile page + its "Edit profile" entry in the user menu (rendered by
                // extra-navigation-items' user-menu override). No navigation item of its own — it
                // is reached from the user menu.
                FilamentEditProfilePlugin::make()->shouldRegisterNavigation(false),
                // Cross-panel shortcuts (Calendar + the legacy admin), rendered next to the user
                // menu on every panel that shares this config — realising the "Calendar + Admin
                // links in the topbar, shared with the frontend" that each panel used to carry as
                // its own duplicated navigationItems(). Each item hides itself when it does not
                // apply. See NavigationItemsPlugin (magicoli/extra-navigation-items).
                NavigationItemsPlugin::make()->items([
                    NavigationItem::make('calendar')
                        ->label(fn(): string => __('app.calendar'))
                        ->icon('heroicon-o-calendar-date-range')
                        ->url(fn(): string => route('calendar'))
                        ->visible(fn(): bool => auth()->check()),
                    NavigationItem::make("dashboard")
                        ->label(fn(): string => __('app.dashboard'))
                        ->icon('bi-luggage')
                        // ->group('legacy')
                        ->url(fn(): string => route('filament.app.pages.dashboard'))
                        // Nothing to offer a visitor who cannot enter it. Owners rather than every
                        // account, in truth — that distinction arrives with the tenants.
                        ->visible(fn(): bool => auth()->check()),
                    NavigationItem::make('legacy-admin')
                        ->label(fn(): string => __('app.obsolete'))
                        ->icon('ri-dashboard-line')
                        ->url(fn(): string => route('admin.dashboard'))
                        ->visible(fn(): bool => (bool) auth()->user()?->isAdmin()),
                ]),
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
     * The panels' built stylesheets and scripts, resolved through Vite.
     *
     * Vite::asset(), not resource_path(): Filament publishes a local path verbatim, which would
     * ship @import and @apply straight to the browser. Everything under resources/ is built, and
     * the panels are served what the build produced.
     *
     * Wrapped so a transiently missing manifest cannot crash panel registration — the dev watcher
     * rewriting manifest.json, or a test run against an unbuilt tree, would otherwise throw here on
     * every request. When it cannot resolve, the assets simply do not load for that request and
     * return on the next one; in production the manifest is always present, so this never triggers.
     *
     * @return array<int, Css|Js>
     */
    protected function panelViteAssets(): array
    {
        try {
            return [
                Css::make('glass-stylesheet', Vite::asset('resources/css/glass.css')),
                Css::make('panels-stylesheet', Vite::asset('resources/css/panels.css')),
                Css::make('legacy-stylesheet', Vite::asset('resources/css/legacy.css')),
                Js::make('panels-script', Vite::asset('resources/js/panels.js')),
            ];
        } catch (\Throwable) {
            return [];
        }
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
