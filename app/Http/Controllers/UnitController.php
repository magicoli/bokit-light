<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\IcalSource;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Show the form for editing the unit
     */
    public function edit(Unit $unit)
    {
        // Check access: admin or user has access to the unit's property
        if (!auth()->user()->isAdmin()) {
            $hasAccess = $unit->property->users()
                ->where('users.id', auth()->id())
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to this unit.');
            }
        }
        
        $unit->load(['property', 'icalSources']);
        
        return view('units.edit', [
            'unit' => $unit,
        ]);
    }
    
    /**
     * Update the unit and its sources
     */
    public function update(Request $request, Unit $unit)
    {
        // Check access
        if (!auth()->user()->isAdmin()) {
            $hasAccess = $unit->property->users()
                ->where('users.id', auth()->id())
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to this unit.');
            }
        }
        
        // Validate basic unit info
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
        ]);
        
        // Update unit
        $unit->update($validated);
        
        // Handle sources
        if ($request->has('sources')) {
            // Get existing source IDs
            $existingIds = $unit->icalSources->pluck('id')->toArray();
            $keepIds = [];
            
            foreach ($request->sources as $sourceData) {
                if (isset($sourceData['url']) && !empty($sourceData['url'])) {
                    if (isset($sourceData['id']) && in_array($sourceData['id'], $existingIds)) {
                        // Update existing source
                        $source = IcalSource::find($sourceData['id']);
                        $source->update([
                            'type' => $sourceData['type'] ?? 'ical',
                            'url' => $sourceData['url'],
                            'name' => parse_url($sourceData['url'], PHP_URL_HOST) ?? 'External Calendar',
                        ]);
                        $keepIds[] = $source->id;
                    } else {
                        // Create new source
                        $source = $unit->icalSources()->create([
                            'type' => $sourceData['type'] ?? 'ical',
                            'url' => $sourceData['url'],
                            'name' => parse_url($sourceData['url'], PHP_URL_HOST) ?? 'External Calendar',
                        ]);
                        $keepIds[] = $source->id;
                    }
                }
            }
            
            // Delete sources not in the keep list
            IcalSource::where('unit_id', $unit->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
        }
        
        return redirect()
            ->route('units.edit', $unit)
            ->with('success', 'Unit updated successfully!');
    }
}
