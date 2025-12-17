<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WelcomeController extends Controller
{
    public function index()
    {
        $readmePath = base_path('README.md');
        $readmeContent = File::exists($readmePath) 
            ? File::get($readmePath)
            : '# Welcome to Bokit

This is a Laravel-based calendar management application for vacation rental properties.';

        return view('welcome', [
            'readme' => $readmeContent,
        ]);
    }
}
