<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Show user profile/settings page
     */
    public function settings()
    {
        return view('user.settings');
    }
}
