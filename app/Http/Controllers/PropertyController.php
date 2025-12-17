<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Show properties list
     */
    public function index()
    {
        // Filter by user access if not admin
        $query = Property::with('units');
        
        if (!auth()->user()->isAdmin()) {
            $query->whereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            });
        }
        
        $properties = $query->get();
        
        return view('properties.index', [
            'properties' => $properties,
        ]);
    }
}
