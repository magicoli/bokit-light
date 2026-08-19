<?php

namespace App\Providers\Filament;

use App\Filament\Concerns\HasSharedPanelConfig;
use App\Filament\Pages\Dashboard;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Magicoli\ExtraNavigationItems\NavigationItemsPlugin;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
use Magicoli\TwoWayTicket\ReportIssuePlugin;
use Magicoli\TwoWayTicket\TicketsPlugin;
use Filament\View\PanelsRenderHook;

class MainPanelProvider extends PanelProvider
{
    use HasSharedPanelConfig;

    public function panel(Panel $panel): Panel
    {
        $panel = $this->applyCommonConfig($panel, 'main', '');

        return $panel
            // Specific config, this panel only
            // ->id('main')
            // ->path('')
            // ->login() // Already set by applyCommonConfig()
            ->default()
            ->topNavigation()
            ->sidebarCollapsibleOnDesktop(false) // Disabled for top navigation, overrides common config
            ->sidebarFullyCollapsibleOnDesktop(false) // Disabled for top navigation, overrides common config
            ->maxContentWidth(Width::Full)
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
                NavigationItemsPlugin::make()->renderHook(PanelsRenderHook::FOOTER)->items(self::footerItems()),
            ]);
    }
}
