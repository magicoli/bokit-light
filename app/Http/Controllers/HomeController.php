<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class HomeController extends Controller
{
    public function index()
    {
        // ABOUT.md, not README.md: a repo readme is written for whoever clones the project, not
        // for whoever books a holiday. Which file it comes from is an implementation detail — what
        // this serves is the home page.
        $readmePath = base_path('ABOUT.md');
        $readmeContent = File::exists($readmePath) 
            ? File::get($readmePath)
            : '# Welcome to Bokit

This is a Laravel-based calendar management application for vacation rental properties.';

        return view('home', [
            'readme' => $readmeContent,
            'wallpapers' => $this->wallpapers(),
        ]);
    }

    /**
     * The wallpapers available for each theme, as public URLs.
     *
     * They are read from the disk rather than listed anywhere: adding a photograph is dropping a
     * file in assets/images/wallpapers/{light,dark}/ and running the build, which writes the
     * stripped JPEG this reads. An empty folder is not a problem — the page keeps its gradient.
     *
     * @return array{light: list<string>, dark: list<string>}
     */
    private function wallpapers(): array
    {
        $theme = function (string $theme): array {
            $files = File::glob(public_path("images/wallpapers/{$theme}/*.{jpg,jpeg,webp,avif}"), GLOB_BRACE) ?: [];

            sort($files);

            return array_map(
                fn (string $path): string => asset("images/wallpapers/{$theme}/" . basename($path)),
                $files,
            );
        };

        return ['light' => $theme('light'), 'dark' => $theme('dark')];
    }
}
