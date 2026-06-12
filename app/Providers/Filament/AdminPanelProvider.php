<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Http\Middleware\SetLocale;
use CraftForge\FilamentLanguageSwitcher\FilamentLanguageSwitcherPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): View => view('filament.tables.booking-inline-filters'),
            scopes: ListBookings::class,
        );

        // Booking status colors — same conventions as resources/css/_theme.css
        // (the panel does not load the frontend theme). paid/due refine
        // 'confirmed' by payment state.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString('<style>
                :root {
                    --color-paid: #84cc16;
                    --color-due: #14b8a6;
                    --color-option: #f59e0bc0;
                    --color-quote: #eab30880;
                    --color-blocked: #00000080;
                    --color-cancelled: #88888880;
                    --color-vanished: #88888880;
                    --color-deleted: #88888880;
                    --color-unknown: #888888;
                }
                tr.booking-status-paid td { background-color: color-mix(in srgb, var(--color-paid) 18%, transparent) !important; }
                tr.booking-status-due td { background-color: color-mix(in srgb, var(--color-due) 18%, transparent) !important; }
                tr.booking-status-option td { background-color: color-mix(in srgb, var(--color-option) 22%, transparent) !important; }
                tr.booking-status-quote td { background-color: color-mix(in srgb, var(--color-quote) 40%, transparent) !important; }
                .bokit-mini-list .fi-ta-table > thead { display: none; }
            </style>'),
        );

        // Group booking row styling — injected once into the panel <head>.
        // Summary rows (unit_id=NULL) get a warm yellow background.
        // Member rows (individual units within a group) get a lighter tint.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString('<style>
                tr.booking-group-summary td { background-color: #fef9c3 !important; }
                tr.booking-group-member  td { background-color: #fefce8 !important; }
                .dark tr.booking-group-summary td { background-color: #422006 !important; }
                .dark tr.booking-group-member  td { background-color: #2d1b07 !important; }
            </style>'),
        );

        // CSS for the shared nav/top-links partial rendered inside the Filament topbar.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString('<style>
                .fi-topbar .nav-link {
                    font-size:.875rem; font-weight:500; color:rgb(55 65 81);
                    padding:.375rem .75rem; border-radius:.375rem; text-decoration:none;
                    display:inline-flex; align-items:center; transition:background .15s;
                }
                .fi-topbar .nav-link:hover { background:rgba(0,0,0,.05); }
                .fi-topbar .badge-manage { background:rgb(219 234 254); color:rgb(29 78 216); }
                .fi-topbar .badge-admin  { background:rgb(254 226 226); color:rgb(153 27 27); }
                .fi-topbar .dropdown { position:relative; display:flex; align-items:center; }
                .fi-topbar .dropdown-button {
                    display:flex; align-items:center; gap:.25rem; text-decoration:none;
                }
                .fi-topbar .dropdown-button svg { width:1rem; height:1rem; }
                .fi-topbar .dropdown-menu {
                    position:absolute; top:100%; right:0; min-width:12rem;
                    background:white; border-radius:.375rem;
                    box-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);
                    padding:.25rem 0; z-index:9999; border:1px solid rgb(229 231 235);
                }
                .fi-topbar .dropdown-item {
                    display:block; padding:.5rem 1rem; font-size:.875rem;
                    color:rgb(55 65 81); text-decoration:none;
                }
                .fi-topbar .dropdown-item:hover { background:rgb(249 250 251); }
                .dark .fi-topbar .nav-link { color:rgb(209 213 219); }
                .dark .fi-topbar .nav-link:hover { background:rgba(255,255,255,.05); }
                .dark .fi-topbar .badge-manage { background:rgb(30 58 138); color:rgb(191 219 254); }
                .dark .fi-topbar .badge-admin  { background:rgb(127 29 29); color:rgb(254 202 202); }
                .dark .fi-topbar .dropdown-menu {
                    background:rgb(17 24 39); border-color:rgba(255,255,255,.1);
                }
                .dark .fi-topbar .dropdown-item { color:rgb(209 213 219); }
                .dark .fi-topbar .dropdown-item:hover { background:rgba(255,255,255,.05); }
            </style>'),
        );

        // Calendar + Admin links in the Filament topbar (shared partial with frontend).
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => view('nav.top-links'),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->breadcrumbs(false)
            ->homeUrl('/')
            ->brandLogo('/images/logo.png')
            ->brandLogoHeight(fn () => request()->is('login', '*/login') ? '128px' : '48px')
            // ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\Filament\Widgets')
            ->widgets([
            ])
            ->plugins([
                FilamentLanguageSwitcherPlugin::make()
                    ->locales(['en', 'fr'])
                    ->rememberLocale()
                    // ->renderHook(PanelsRenderHook::USER_MENU_PROFILE_AFTER)
                    ->renderHook(PanelsRenderHook::USER_MENU_AFTER)
                    ->showOnAuthPages(),

            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
