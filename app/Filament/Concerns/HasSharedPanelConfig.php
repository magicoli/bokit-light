<?php

namespace App\Filament\Concerns;

use App\Filament\Profile\TimezoneForm;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;
use Magicoli\ExtraNavigationItems\NavigationItemsPlugin;
use Magicoli\TwoWayTicket\ReportIssuePlugin;

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
            // ->brandLogo('/images/logo.png')
            // The current tenant's own logo (Property.logo, dev/project-timezone-and-tenant-settings.md)
            // replaces the app-wide one when set — Filament::getTenant() is null outside a
            // tenant-scoped panel (main/admin), safely falling through to the app default there.
            ->brandLogo(fn (): ?string => ($logo = Filament::getTenant()?->logo)
                ? Storage::disk('public')->url($logo)
                : (config('app.logo') ?: null))
            // ->brandLogoHeight(fn () => request()->is('login', '*/login') ? '128px' : '48px')
            ->brandLogoHeight('48px')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Teal,
            ])
            ->font('Switzer', url: Vite::asset('resources/css/fonts.css'), provider: LocalFontProvider::class)
            ->assets([
                Css::make('panels-stylesheet', Vite::asset('resources/css/panels.css')),
                // Css::make('glass-stylesheet', Vite::asset('resources/css/glass.css')),
                Js::make('panels-script', Vite::asset('resources/js/panels.js')),
                // Css::make('legacy-stylesheet', Vite::asset('resources/css/legacy.css')),
            ])
            // ->breadcrumbs(false)
            // ->sidebarCollapsibleOnDesktop()
            ->plugins([
                ReportIssuePlugin::make(),
                // The edit-profile page + its "Edit profile" entry in the user menu (rendered by
                // extra-navigation-items' user-menu override). No navigation item of its own — it
                // is reached from the user menu.
                // ->shouldShowSanctumTokens(): lets any user issue/revoke their own personal
                // access tokens from their profile page — the MCP server (routes/mcp.php) and
                // any other Sanctum-authenticated API consumer authenticate with one of these.
                // bokit:issue-api-token stays for maintenance/debug only, never the primary
                // path — the app must be fully manageable from the UI.
                FilamentEditProfilePlugin::make()
                    ->shouldRegisterNavigation(false)
                    ->shouldShowSanctumTokens()
                    // The package's own bundled fields stop at name/email/locale/avatar - timezone
                    // is app-specific, added as its own section rather than through the package's
                    // custom_fields (that serializes into one JSON blob, the wrong shape for a
                    // first-class column like User.timezone).
                    ->customProfileComponents([TimezoneForm::class]),
                // Cross-panel shortcuts (Calendar + the legacy admin), rendered next to the user
                // menu on every panel that shares this config — realising the "Calendar + Admin
                // links in the topbar, shared with the frontend" that each panel used to carry as
                // its own duplicated navigationItems(). Each item hides itself when it does not
                // apply. See NavigationItemsPlugin (magicoli/extra-navigation-items).
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
}
