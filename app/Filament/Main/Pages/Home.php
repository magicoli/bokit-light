<?php

namespace App\Filament\Main\Pages;

use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The site's front page, served by the main panel at the root.
 *
 * The route is NOT declared in routes/web.php: a panel registers the routes of the pages it
 * discovers, with its own middleware, and declaring '/' by hand would shadow it.
 */
class Home extends Page
{
    protected string $view = 'filament.main.pages.home';

    protected static bool $shouldRegisterNavigation = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getTitle(): string
    {
        return config('app.name');
    }

    /**
     * ABOUT.md, rendered here rather than by a script in the browser.
     *
     * README.md is written for whoever clones the project; this is for whoever books a holiday.
     * Which file it comes from is an implementation detail — what this serves is the home page.
     */
    public function getContentHtml(): string
    {
        $path = base_path('ABOUT.md');

        return Str::markdown(File::exists($path) ? File::get($path) : '# ' . config('app.name'));
    }
}
