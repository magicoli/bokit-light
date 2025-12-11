#!/usr/bin/env php
<?php
/**
 * Quick database inspection script
 * Usage: php inspect-db.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Bokit Database Inspection\n";
echo "============================\n\n";

// Properties
$properties = \App\Models\Property::orderBy('id')->get();
echo "📦 Properties (" . $properties->count() . "):\n";
foreach ($properties as $property) {
    echo "  [{$property->id}] {$property->name} (slug: {$property->slug})\n";
    
    // Units for this property
    $units = \App\Models\Unit::where('property_id', $property->id)
        ->orderBy('id')
        ->get();
    
    foreach ($units as $unit) {
        $activeText = $unit->is_active ? '✓' : '✗';
        echo "    └─ [{$unit->id}] {$unit->name} {$activeText} (color: {$unit->color})\n";
        
        // iCal sources for this unit
        $sources = \App\Models\IcalSource::where('unit_id', $unit->id)->get();
        foreach ($sources as $source) {
            $enabledText = $source->sync_enabled ? '✓' : '✗';
            $lastSync = $source->last_synced_at ? $source->last_synced_at->format('Y-m-d H:i') : 'never';
            $status = $source->last_sync_status ?? 'unknown';
            echo "        └─ [{$source->id}] {$source->name} {$enabledText} (last: {$lastSync}, status: {$status})\n";
            if ($source->last_sync_error) {
                echo "           ERROR: " . substr($source->last_sync_error, 0, 80) . "\n";
            }
        }
        
        // Count bookings
        $bookingCount = \App\Models\Booking::where('unit_id', $unit->id)
            ->whereNull('deleted_at')
            ->count();
        echo "        └─ 📅 {$bookingCount} booking(s)\n";
    }
    echo "\n";
}

// Overall stats
echo "\n📊 Overall Stats:\n";
$totalUnits = \App\Models\Unit::count();
$activeUnits = \App\Models\Unit::where('is_active', true)->count();
$totalSources = \App\Models\IcalSource::count();
$enabledSources = \App\Models\IcalSource::where('sync_enabled', true)->count();
$totalBookings = \App\Models\Booking::whereNull('deleted_at')->count();
$futureBookings = \App\Models\Booking::whereNull('deleted_at')
    ->where('check_out', '>=', now()->format('Y-m-d'))
    ->count();

echo "  Units: {$activeUnits}/{$totalUnits} active\n";
echo "  iCal Sources: {$enabledSources}/{$totalSources} enabled\n";
echo "  Bookings: {$futureBookings}/{$totalBookings} future/total\n";

// Check cache for auto-sync
$lastAutoSync = \Illuminate\Support\Facades\Cache::get('last_auto_sync', 0);
if ($lastAutoSync > 0) {
    $lastSyncTime = date('Y-m-d H:i:s', $lastAutoSync);
    $nextSync = date('Y-m-d H:i:s', $lastAutoSync + 3600);
    echo "\n⏰ Auto-sync:\n";
    echo "  Last sync: {$lastSyncTime}\n";
    echo "  Next sync: {$nextSync} (assuming 1h interval)\n";
} else {
    echo "\n⏰ Auto-sync: Never run\n";
}

echo "\n✅ Done!\n";
