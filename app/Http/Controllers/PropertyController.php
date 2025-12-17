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
     * Show single property calendar
     */
    public function show(Property $property)
    {
        // Check access: admin or user has access to the property
        if (!auth()->user()->isAdmin()) {
            $hasAccess = $property->users()
                ->where('users.id', auth()->id())
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to this property.');
            }
        }
        
        // For now, redirect to dashboard with property filter
        // TODO: Create dedicated property view with tabs (calendar/edit)
        return redirect()->route('dashboard', ['property' => $property->slug]);
    }
}
