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
        // Filter by user authorization
        $properties = Property::with('units')->forUser()->get();
        
        return view('properties', [
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
