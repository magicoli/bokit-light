<?php

namespace App\Providers\Filament;

use App\Filament\Concerns\HasSharedPanelConfig;
use App\Filament\Pages\Dashboard;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Magicoli\ExtraNavigationItems\NavigationItemsPlugin;

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
            // The one login route for the whole app: /login on the main panel.
            ->login()
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
                        ->url(fn (): string => route('filament.app.pages.calendar'))
                        ->visible(fn (): bool => auth()->check()),
                    NavigationItem::make('dashboard')
                        ->label(fn (): string => __('app.dashboard'))
                        ->icon('bi-luggage')
                        // ->group('legacy')
                        ->url(fn (): string => route('filament.app.pages.dashboard'))
                        // Nothing to offer a visitor who cannot enter it. Owners rather than every
                        // account, in truth — that distinction arrives with the tenants.
                        ->visible(fn (): bool => auth()->check()),
                    // NavigationItem::make('legacy-admin')
                    //     ->label(fn (): string => __('app.obsolete'))
                    //     ->icon('ri-dashboard-line')
                    //     ->url(fn (): string => route('admin.dashboard'))
                    //     ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                ]),
            ]);
    }
}
