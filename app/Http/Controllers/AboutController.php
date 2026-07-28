<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class AboutController extends Controller
{
    public function index()
    {
        // ABOUT.md, not README.md: this renders the public home page, and a repo readme is written
        // for whoever clones the project, not for whoever books a holiday.
        $readmePath = base_path('ABOUT.md');
        $readmeContent = File::exists($readmePath) 
            ? File::get($readmePath)
            : '# Welcome to Bokit

This is a Laravel-based calendar management application for vacation rental properties.';

        return view('about', [
            'readme' => $readmeContent,
        ]);
    }
}
