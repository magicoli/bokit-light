<?php

namespace Modules\Hbook;

use App\Filament\Resources\Units\Schemas\UnitForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Get as FormsGet;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Illuminate\Support\ServiceProvider;
use Modules\Hbook\Commands\HbookImportCommand;
use Modules\Hbook\Commands\MultipassImportCommand;

class HbookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                HbookImportCommand::class,
                MultipassImportCommand::class,
            ]);
        }
    }

    /**
     * Get HBook units from WordPress API
     * 
     * HBook is configured site-wide in WordPress, not per-property.
     * We use the current unit's property configuration to connect to WordPress,
     * but we fetch all units from the WordPress site, not filtered by property.
     */
    public static function getHbookUnitsFromWordPress(?int $unitId = null): array
    {
        \Illuminate\Support\Facades\Log::info("HBook: getHbookUnitsFromWordPress() called", ['unit_id' => $unitId]);
        
        try {
            // Get the current unit's property from request or parameter
            if (! $unitId) {
                // Try to get from request first
                $unitId = request()->route('record');
                if ($unitId) {
                    \Illuminate\Support\Facades\Log::info("HBook: Using unit ID from request", ['unit_id' => $unitId]);
                } else {
                    // Fallback: try to get the first unit from the database
                    // This is a temporary solution until we find the right way to get the current unit ID
                    $firstUnit = \App\Models\Unit::first();
                    if ($firstUnit) {
                        $unitId = $firstUnit->id;
                        \Illuminate\Support\Facades\Log::info("HBook: Using fallback unit ID from first unit in database", ['unit_id' => $unitId]);
                    }
                }
            }
            
            if (! $unitId) {
                \Illuminate\Support\Facades\Log::warning("HBook: No unit ID available");
                return [];
            }
            
            $unit = \App\Models\Unit::find($unitId);
            
            if (! $unit) {
                \Illuminate\Support\Facades\Log::warning("HBook: Unit not found", ['unit_id' => $unitId]);
                return [];
            }
            
            $property = $unit->property;
            
            if (! $property) {
                \Illuminate\Support\Facades\Log::warning("HBook: Unit has no property", ['unit_id' => $unitId]);
                return [];
            }
            
            $wpConnector = new \Modules\WpConnector\Services\WpConnectorService($property);
            
            if (! $wpConnector->isConfigured()) {
                \Illuminate\Support\Facades\Log::warning("HBook: WP Connector not configured for property", [
                    'property' => $property->id,
                    'unit' => $unit->id,
                ]);
                return [];
            }
            
            \Illuminate\Support\Facades\Log::info("HBook: Fetching all units from WordPress site", [
                'property' => $property->id,
                'unit' => $unit->id,
                'wp_url' => $property->options['wp_url'],
            ]);
            
            // Get all HBook units from the WordPress site
            $response = $wpConnector->get('/wp-json/bokit/v1/hbook-units');
            
            \Illuminate\Support\Facades\Log::info("HBook: WordPress API response", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            if (! $response->successful()) {
                \Illuminate\Support\Facades\Log::warning("HBook: WordPress API returned non-success status", [
                    'status' => $response->status(),
                ]);
                return [];
            }
            
            $units = $response->json('units', []);
            \Illuminate\Support\Facades\Log::info("HBook: Parsed units from WordPress", ['count' => count($units)]);
            
            // Return flat [id => "name (post_title)"] for Filament Select options.
            $formatted = [];
            foreach ($units as $unit) {
                $id    = $unit['id'] ?? $unit['ID'] ?? '';
                $name  = $unit['name'] ?? 'Unknown';
                $title = $unit['post_title'] ?? '';
                $label = $title ? "{$name} ({$title})" : $name;
                $formatted[$id] = $label;
            }

            \Illuminate\Support\Facades\Log::info("HBook: Returning formatted units", [
                'count' => count($formatted),
            ]);

            return $formatted;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("HBook: Exception fetching units", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
}
