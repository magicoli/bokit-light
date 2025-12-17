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
    
    /**
     * Show single property (public page)
     */
    public function show(Property $property)
    {
        // Public page - no auth required
        // Load units with their sources for authorized users
        $property->load('units.icalSources');
        
        return view('properties.show', [
            'property' => $property,
        ]);
    }
}
