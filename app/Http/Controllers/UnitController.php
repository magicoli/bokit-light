<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Unit;
use App\Models\IcalSource;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Show the public unit page (placeholder)
     */
    public function show(Property $property, Unit $unit)
    {
        // Verify unit belongs to property
        if ($unit->property_id !== $property->id) {
            abort(404);
        }

        // For now, show a simple placeholder page
        // TODO: Implement full public unit page with booking calendar
        return view("units.show", [
            "unit" => $unit,
        ]);
    }

    /**
     * Show the form for editing the unit
     */
    public function edit(Property $property, Unit $unit)
    {
        // Verify unit belongs to property
        if ($unit->property_id !== $property->id) {
            abort(404);
        }

        // Check access: admin or user has access to the unit's property
        if (!user_can('super_admin')) {
            $hasAccess = $unit->property
                ->users()
                ->where("users.id", auth()->id())
                ->exists();

            if (!$hasAccess) {
                abort(403, "You do not have access to this unit.");
            }
        }

        $unit->load(["property", "icalSources"]);

        return view("units.edit", [
            "unit" => $unit,
        ]);
    }

    /**
     * Update the unit and its sources
     */
    public function update(Request $request, Property $property, Unit $unit)
    {
        // Verify unit belongs to property
        if ($unit->property_id !== $property->id) {
            abort(404);
        }
        // Check access
        if (!user_can('super_admin')) {
            $hasAccess = $unit->property
                ->users()
                ->where("users.id", auth()->id())
                ->exists();

            if (!$hasAccess) {
                abort(403, "You do not have access to this unit.");
            }
        }

        // Validate basic unit info
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "slug" => "required|string|max:255",
        ]);

        // Update unit
        $unit->update($validated);

        // Handle sources
        if ($request->has("sources")) {
            // Get existing source IDs
            $existingIds = $unit->icalSources->pluck("id")->toArray();
            $keepIds = [];

            foreach ($request->sources as $sourceData) {
                if (isset($sourceData["url"]) && !empty($sourceData["url"])) {
                    if (
                        isset($sourceData["id"]) &&
                        in_array($sourceData["id"], $existingIds)
                    ) {
                        // Update existing source
                        $source = IcalSource::find($sourceData["id"]);
                        $source->update([
                            "type" => $sourceData["type"] ?? "ical",
                            "url" => $sourceData["url"],
                            "name" =>
                                parse_url($sourceData["url"], PHP_URL_HOST) ??
                                __("app.external_calendar"),
                        ]);
                        $keepIds[] = $source->id;
                    } else {
                        // Create new source
                        $source = $unit->icalSources()->create([
                            "type" => $sourceData["type"] ?? "ical",
                            "url" => $sourceData["url"],
                            "name" =>
                                parse_url($sourceData["url"], PHP_URL_HOST) ??
                                __("app.external_calendar"),
                        ]);
                        $keepIds[] = $source->id;
                    }
                }
            }

            // Delete sources not in the keep list
            IcalSource::where("unit_id", $unit->id)
                ->whereNotIn("id", $keepIds)
                ->delete();
        }

        return redirect()
            ->route("units.edit", [$property, $unit])
            ->with("success", __("forms.unit_updated_successfully"));
    }
}
