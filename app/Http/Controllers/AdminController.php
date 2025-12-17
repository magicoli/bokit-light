<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Show admin settings page
     */
    public function settings()
    {
        return view('admin.settings');
    }
}
