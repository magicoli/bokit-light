<?php

namespace App\Providers\Filament;

use App\Filament\Concerns\HasSharedPanelConfig;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
use Magicoli\TwoWayTicket\ReportIssuePlugin;
use Magicoli\TwoWayTicket\TicketsPlugin;

class AppPanelProvider extends PanelProvider
{
    public string $panel_id;
    public string $panel_path;

    use HasSharedPanelConfig;

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn(): View => view('filament.tables.booking-inline-filters'),
            scopes: ListBookings::class,
        );

        // Calendar + Admin links in the Filament topbar (shared partial with frontend).
        // FilamentView::registerRenderHook(PanelsRenderHook::USER_MENU_BEFORE, fn(): View => view('nav.top-links'));
    }

    public function panel(Panel $panel): Panel
    {
        $panel = $this->applyCommonConfig($panel, 'app');

        return $panel
            // ->id('app')
            // ->path('app')
            ->default()
            // No ->login(): the app panel has no login route of its own; its guests are sent to
            // the main panel's /login by redirectGuestsTo (bootstrap/app.php).
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->topbar(false)
            ->navigationGroups([
                NavigationGroup::make(__('Deprecated'))->collapsed(),
            ])
            ->navigationItems([
                // Calendar and the legacy admin now come from the shared cross-panel shortcuts in
                // HasSharedPanelConfig, rendered next to the user menu on every panel. What stays
                // here is the panel's own "Deprecated" group of deep legacy links.
                NavigationItem::make('properties')
                    ->label(fn(): string => __('app.properties'))
                    ->icon('heroicon-s-building-office-2')
                    ->group(fn(): string => __('Deprecated'))
                    ->url(fn(): string => route('properties'))
                    ->badge(fn(): string => __('Legacy'))
                    ->visible(fn(): bool => (bool) auth()->user()?->isAdmin()),
                NavigationItem::make('rates')
                    ->label(fn(): string => __('app.rates'))
                    ->icon('heroicon-s-banknotes')
                    ->group(fn(): string => __('Deprecated'))
                    ->url(fn(): string => route('rates'))
                    ->badge(fn(): string => __('Legacy'))
                    ->visible(fn(): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                TicketStatsWidget::make(),
            ])
            ->plugins([
                TicketsPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
