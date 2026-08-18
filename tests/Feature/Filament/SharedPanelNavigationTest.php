<?php

use Filament\Facades\Filament;
use Filament\View\PanelsRenderHook;

it('registers the shared cross-panel shortcuts and the footer on every shared panel', function (string $panelId): void {
    $ids = array_keys(Filament::getPanel($panelId)->getPlugins());

    expect($ids)
        ->toContain('navigation-items-plugin:' . PanelsRenderHook::USER_MENU_BEFORE)
        ->toContain('navigation-items-plugin:' . PanelsRenderHook::FOOTER);
})->with(['main', 'app']);

it('defines two shared shortcuts (calendar, admin) and a single footer credit item', function (): void {
    $shortcuts = (new ReflectionMethod(\App\Providers\Filament\MainPanelProvider::class, 'sharedShortcuts'))
        ->invoke(null);
    $footer = (new ReflectionMethod(\App\Providers\Filament\MainPanelProvider::class, 'footerItems'))
        ->invoke(null);

    expect($shortcuts)->toHaveCount(2)
        ->and($footer)->toHaveCount(1);
});
