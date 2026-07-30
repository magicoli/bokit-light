<?php

namespace App\Filament\Main\Pages;

use Filament\Pages\Page;
use Filament\Panel;

class Home extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    // protected static string|array $withoutRouteMiddleware = [
    //     Authenticate::class,
    // ];

    protected string $view = 'filament.main.pages.home';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getTitle(): string
    {
        return join(' - ', array_filter([$this->getHeading(), config('app.slogan')]));
    }

    /**
     * Title dispayed in the page before content.
     */
    public function getHeading(): string
    {
        return config('app.name') ?: __('Welcome');

        // return ''; // Title is already in the page content
    }
}
