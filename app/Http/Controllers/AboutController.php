<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class AboutController extends Controller
{
    public function index()
    {
        $readmePath = base_path('README.md');
        $readmeContent = File::exists($readmePath) 
            ? File::get($readmePath)
            : '# Welcome to Bokit

This is a Laravel-based calendar management application for vacation rental properties.';

        return view('about', [
            'readme' => $readmeContent,
        ]);
    }
}
