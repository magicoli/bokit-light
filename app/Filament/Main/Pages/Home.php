<?php

namespace App\Filament\Main\Pages;

use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Enums\Width;
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
     * No printed heading: the text opens with its own level one title, and Filament skips the
     * whole header block when there is neither heading nor subheading to show.
     */
    public function getHeading(): string
    {
        return '';
    }

    /**
     * Narrower than the panel, which runs full width for the working screens. A column of prose
     * has a comfortable measure, and it is not the width of a calendar.
     */
    public function getMaxContentWidth(): Width
    {
        return Width::SevenExtraLarge;
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

        return Str::markdown(File::exists($path) ? File::get($path) : '# '.config('app.name'));
    }

    /**
     * The wallpapers available for each theme, as public URLs.
     *
     * Read from disk rather than listed anywhere: adding a photograph is dropping a file in
     * assets/images/wallpapers/{light,dark}/ and running the build, which writes the stripped
     * JPEG this reads. An empty folder is not a problem — the page keeps its gradient. The page
     * hands this to the browser as JSON; the script builds the surfaces it crossfades between.
     *
     * @return array{light: list<string>, dark: list<string>}
     */
    public function wallpapers(): array
    {
        $theme = function (string $theme): array {
            $files = File::glob(public_path("images/wallpapers/{$theme}/*.{jpg,jpeg,webp,avif}"), GLOB_BRACE) ?: [];

            sort($files);

            return array_map(fn (string $path): string => asset(
                "images/wallpapers/{$theme}/".basename($path),
            ), $files);
        };

        return ['light' => $theme('light'), 'dark' => $theme('dark')];
    }
}
