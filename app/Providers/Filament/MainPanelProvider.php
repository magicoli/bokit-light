<?php

namespace App\Providers\Filament;

use App\Filament\Concerns\HasSharedPanelConfig;
use App\Filament\Pages\Dashboard;
use App\Models\Property;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Storage;
use Magicoli\ExtraNavigationItems\NavigationItemsPlugin;

class MainPanelProvider extends PanelProvider
{
    use HasSharedPanelConfig;

    /**
     * The App panel is now tenant-scoped by Property, so its routes need a tenant param — these
     * shortcuts, shown outside that panel (on the public/main panel's user menu), pick the
     * signed-in user's first accessible property as a default landing tenant. Null when the user
     * has none, which the items' own visible() hides.
     */
    private static function defaultTenant(): ?Property
    {
        $user = auth()->user();

        return $user?->getTenants(Filament::getPanel('app'))->first();
    }

    /**
     * Each tenant is its own sub-site (dev/project-app-panel-tenancy.md) — a guest bounced here
     * from a tenant URL should see THAT tenant's branding on the login screen, not the app's own.
     * Filament's real tenant resolution only runs post-auth, so there is no Filament::getTenant()
     * to read yet; the tenant is instead recovered from the URL the guest was actually trying to
     * reach, which Laravel's own auth-redirect flow (redirect()->guest()) already stores in
     * session('url.intended') before sending them here. Scoped to the login route itself so it
     * never leaks tenant branding onto other Main panel pages from a stale session value.
     */
    private static function intendedTenant(): ?Property
    {
        if (! request()->routeIs('filament.main.auth.login')) {
            return null;
        }

        $intended = session('url.intended');
        if (! $intended) {
            return null;
        }

        if (! preg_match('#/app/([^/]+)#', (string) parse_url($intended, PHP_URL_PATH), $matches)) {
            return null;
        }

        return Property::where('slug', $matches[1])->first();
    }

    public function panel(Panel $panel): Panel
    {
        $panel = $this->applyCommonConfig($panel, 'main', '');

        return $panel
            // Specific config, this panel only
            // ->id('main')
            // ->path('')
            // The one login route for the whole app: /login on the main panel.
            ->login()
            // Branding for the login screen specifically (see intendedTenant()) - every other
            // Main panel page keeps the app-wide defaults from HasSharedPanelConfig, since
            // intendedTenant() returns null off the login route.
            ->brandLogo(fn (): ?string => ($logo = self::intendedTenant()?->logo)
                ? Storage::disk('public')->url($logo)
                : (config('app.logo') ?: null))
            ->brandName(fn (): ?string => self::intendedTenant()?->name)
            ->default()
            ->topNavigation()
            // ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Main/Resources'), for: 'App\Filament\Main\Resources')
            ->discoverPages(in: app_path('Filament/Main/Pages'), for: 'App\Filament\Main\Pages')
            ->pages([
                // Dashboard::class,
            ])
            ->navigationItems([
                // Calendar and the legacy admin now come from the shared cross-panel shortcuts in
                // HasSharedPanelConfig, rendered next to the user menu on every panel.
            ])
            ->discoverWidgets(in: app_path('Filament/Main/Widgets'), for: 'App\Filament\Main\Widgets')
            // ->widgets([
            //     // AccountWidget::class,
            //     // FilamentInfoWidget::class,
            // ])
            ->plugins([
                // The credit line at the foot of every panel.
                NavigationItemsPlugin::make()->renderHook(PanelsRenderHook::FOOTER)->items(
                    [
                        NavigationItem::make('credit')->label(fn (): string => config('app.name').' '.config('app.version')),
                        NavigationItem::make('github')
                            ->label('GitHub')
                            ->url('https://github.com/magicoli/bokit-light', shouldOpenInNewTab: true)
                            ->icon('ri-github-line')
                            ->group('External')
                            ->sort(10),
                    ]
                ),
                NavigationItemsPlugin::make()->items([
                    NavigationItem::make('calendar')
                        ->label(fn (): string => __('app.calendar'))
                        ->icon('heroicon-o-calendar-date-range')
                        ->url(fn (): string => route('filament.app.pages.calendar', ['tenant' => self::defaultTenant()]))
                        ->visible(fn (): bool => self::defaultTenant() !== null),
                    NavigationItem::make('dashboard')
                        ->label(fn (): string => __('app.dashboard'))
                        ->icon('bi-luggage')
                        // ->group('legacy')
                        ->url(fn (): string => route('filament.app.pages.dashboard', ['tenant' => self::defaultTenant()]))
                        // Nothing to offer a visitor who cannot enter it, or one with no property yet.
                        ->visible(fn (): bool => self::defaultTenant() !== null),
                    // NavigationItem::make('legacy-admin')
                    //     ->label(fn (): string => __('app.obsolete'))
                    //     ->icon('ri-dashboard-line')
                    //     ->url(fn (): string => route('admin.dashboard'))
                    //     ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                ]),
            ]);
    }
}
