<?php

namespace App\Providers\Filament;

use App\Filament\Concerns\HasSharedPanelConfig;
use App\Filament\Pages\Calendar;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Property;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Contracts\View\View;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
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
            fn (): View => view('filament.tables.booking-inline-filters'),
            scopes: ListBookings::class,
        );
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
            //
            // Property IS the tenant (dev/project-app-panel-tenancy.md) — "chaque propriétaire ne
            // peut voir que ses propres propriétés". slugAttribute: Property already has a slug
            // column, nothing new needed for the URL scheme. No explicit ownershipRelationship:
            // it names the relationship EVERY tenant-scoped resource's own model must have back
            // to the tenant (confirmed live — not "which relationship User uses to list tenants",
            // that's getTenants() below) — its default (camelCase of the tenant model's
            // basename, "property") already matches Booking::property()/Unit::property()/
            // Rate::property() exactly, all pre-existing plain belongsTo relationships.
            ->tenant(Property::class, slugAttribute: 'slug')
            ->sidebarCollapsibleOnDesktop()
            // ->sidebarFullyCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                // No need to include Filament/Pages, auto-discovered
            ])
            ->topbar(false)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn (): string => __('app.admin'))
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(fn (): string => __('app.obsolete'))
                    ->collapsed(),
            ])
            ->navigationItems([
                // Calendar is a real Filament page now (App\Filament\Pages\Calendar) and registers
                // its own navigation entry — no manual item needed here any more. What stays here
                // is the panel's own "Deprecated" group of legacy deep links.
                NavigationItem::make('legacy-admin')
                    ->label(fn (): string => __('app.admin'))
                    ->group(fn (): string => __('app.obsolete'))
                    // ->icon('ri-dashboard-line')
                    ->url(fn (): string => route('admin.dashboard'))
                    ->badge(fn (): string => __('Legacy'))
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                NavigationItem::make('properties')
                    ->label(fn (): string => __('app.properties'))
                    // ->icon('heroicon-s-building-office-2')
                    ->group(fn (): string => __('app.obsolete'))
                    ->url(fn (): string => route('properties'))
                    ->badge(fn (): string => __('Legacy'))
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                NavigationItem::make('rates')
                    ->label(fn (): string => __('app.rates'))
                    // ->icon('heroicon-s-banknotes')
                    ->group(fn (): string => __('app.obsolete'))
                    ->url(fn (): string => route('rates'))
                    ->badge(fn (): string => __('Legacy'))
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                TicketStatsWidget::make(),
            ])
            ->plugins([
                TicketsPlugin::make()->group(fn (): string => __('app.admin'))->sort(90),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
