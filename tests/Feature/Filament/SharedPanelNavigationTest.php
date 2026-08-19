<?php

use Filament\Facades\Filament;
use Filament\View\PanelsRenderHook;

it('registers the shared cross-panel shortcuts next to the user menu on every shared panel', function (string $panelId): void {
    expect(array_keys(Filament::getPanel($panelId)->getPlugins()))
        ->toContain('navigation-items-plugin:' . PanelsRenderHook::USER_MENU_BEFORE);
})->with(['main', 'app']);

it('renders the footer credit line on the main panel only', function (): void {
    $footerId = 'navigation-items-plugin:' . PanelsRenderHook::FOOTER;

    expect(array_keys(Filament::getPanel('main')->getPlugins()))->toContain($footerId)
        ->and(array_keys(Filament::getPanel('app')->getPlugins()))->not->toContain($footerId);
});

it('gives every shared panel the edit-profile page reached from the user menu', function (string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    expect(\Illuminate\Support\Facades\Route::has(
        \Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage::getRouteName(),
    ))->toBeTrue();
})->with(['main', 'app']);
