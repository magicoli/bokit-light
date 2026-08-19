<?php

use Filament\Facades\Filament;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Route;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;
use Magicoli\ExtraNavigationItems\NavigationItemsPlugin;

/**
 * The render hooks every NavigationItemsPlugin on a panel targets. Each registration keeps its own
 * id now (the menus merge in the package's registry), so we look at the instances themselves.
 *
 * @return array<int, string>
 */
function navigationItemHooks(string $panelId): array
{
    return collect(Filament::getPanel($panelId)->getPlugins())
        ->filter(fn ($plugin): bool => $plugin instanceof NavigationItemsPlugin)
        ->map(fn ($plugin): string => (new ReflectionProperty($plugin, 'renderHookName'))->getValue($plugin))
        ->values()
        ->all();
}

describe('Shared panel nav', function () {
    test('registers the shared cross-panel shortcuts next to the user menu on every shared panel', function (string $panelId): void {
        expect(navigationItemHooks($panelId))->toContain(PanelsRenderHook::USER_MENU_BEFORE);
    })->with(['main', 'app']);

    test('renders the footer credit line on the main panel only', function (): void {
        expect(navigationItemHooks('main'))->toContain(PanelsRenderHook::FOOTER)
            ->and(navigationItemHooks('app'))->not->toContain(PanelsRenderHook::FOOTER);
    });

    test('gives every shared panel the edit-profile page reached from the user menu', function (string $panelId): void {
        Filament::setCurrentPanel(Filament::getPanel($panelId));

        expect(Route::has(EditProfilePage::getRouteName()))->toBeTrue();
    })->with(['main', 'app']);
});
