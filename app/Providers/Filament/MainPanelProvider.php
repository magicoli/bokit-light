<?php

namespace App\Providers\Filament;

use App\Filament\Concerns\HasSharedPanelConfig;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
use Magicoli\TwoWayTicket\ReportIssuePlugin;
use Magicoli\TwoWayTicket\TicketsPlugin;

// use Filament\Facades\Filament;
// use Filament\Http\Middleware\Authenticate;
// use Filament\Http\Middleware\AuthenticateSession;
// use Filament\Http\Middleware\DisableBladeIconComponents;
// use Filament\Http\Middleware\DispatchServingFilamentEvent;
// use Filament\Pages\Dashboard;
// use Filament\Support\Assets\Css;
// use Filament\Support\Assets\Js;
// use Filament\Support\Colors\Color;
// use Filament\View\PanelsRenderHook;
// use Filament\Widgets\AccountWidget;
// use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
// use Illuminate\Cookie\Middleware\EncryptCookies;
// use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
// use Illuminate\Routing\Middleware\SubstituteBindings;
// use Illuminate\Session\Middleware\StartSession;
// use Illuminate\View\Middleware\ShareErrorsFromSession;
// use Magicoli\TwoWayTicket\Filament\Resources\Tickets\Widgets\TicketStatsWidget;
// use Magicoli\TwoWayTicket\ReportIssuePlugin;
// use Magicoli\TwoWayTicket\TicketsPlugin;

class MainPanelProvider extends PanelProvider
{
    use HasSharedPanelConfig;

    public function panel(Panel $panel): Panel
    {
        $panel = $this->applyCommonConfig($panel);
        return $panel
            // Specific config, this panel only
            ->id('main')
            ->path('')
            ->default()
            ->topNavigation()
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Main/Resources'), for: 'App\Filament\Main\Resources')
            ->discoverPages(in: app_path('Filament/Main/Pages'), for: 'App\Filament\Main\Pages')
            ->pages([
                // Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Main/Widgets'), for: 'App\Filament\Main\Widgets')
            ->widgets([
                AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->plugins([
                ReportIssuePlugin::make(),
            ]);
    }
}
