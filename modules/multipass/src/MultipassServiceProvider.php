<?php

namespace Modules\Multipass;

use App\Filament\Resources\Units\Schemas\UnitForm;
use App\Models\Unit;
use App\Services\SyncRegistry;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\Multipass\Services\MultipassConnector;
use Modules\WpConnector\Services\WpConnectorService;

class MultipassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // No extension needed - options are set directly in UnitForm

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\MultipassImportCommand::class,
            ]);
        }

        $registry = $this->app->make(SyncRegistry::class);
        $registry->register(new MultipassConnector);
    }

    /**
     * Get Multipass units from WordPress API
     *
     * Multipass is configured site-wide in WordPress, not per-property.
     * We use the current unit's property configuration to connect to WordPress,
     * but we fetch all units from the WordPress site, not filtered by property.
     */
    public static function getMultipassUnitsFromWordPress(?int $unitId = null): array
    {
        Log::info('Multipass: getMultipassUnitsFromWordPress() called', ['unit_id' => $unitId]);

        try {
            // Get the current unit's property from request or parameter
            if (! $unitId) {
                // Try to get from request first
                $unitId = request()->route('record');
                if ($unitId) {
                    Log::info('Multipass: Using unit ID from request', ['unit_id' => $unitId]);
                } else {
                    // Fallback: try to get the first unit from the database
                    // This is a temporary solution until we find the right way to get the current unit ID
                    $firstUnit = Unit::first();
                    if ($firstUnit) {
                        $unitId = $firstUnit->id;
                        Log::info('Multipass: Using fallback unit ID from first unit in database', ['unit_id' => $unitId]);
                    }
                }
            }

            if (! $unitId) {
                Log::warning('Multipass: No unit ID available');

                return [];
            }

            $unit = Unit::find($unitId);

            if (! $unit) {
                Log::warning('Multipass: Unit not found', ['unit_id' => $unitId]);

                return [];
            }

            $property = $unit->property;

            if (! $property) {
                Log::warning('Multipass: Unit has no property', ['unit_id' => $unitId]);

                return [];
            }

            $wpConnector = new WpConnectorService($property);

            if (! $wpConnector->isConfigured()) {
                Log::warning('Multipass: WP Connector not configured for property', [
                    'property' => $property->id,
                    'unit' => $unit->id,
                ]);

                return [];
            }

            Log::info('Multipass: Fetching all units from WordPress site', [
                'property' => $property->id,
                'unit' => $unit->id,
                'wp_url' => $property->options['wp_url'],
            ]);

            // Get all Multipass units from the WordPress site
            $response = $wpConnector->get('/wp-json/bokit/v1/multipass-units');

            Log::info('Multipass: WordPress API response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (! $response->successful()) {
                Log::warning('Multipass: WordPress API returned non-success status', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $units = $response->json('units', []);
            Log::info('Multipass: Parsed units from WordPress', ['count' => count($units)]);

            // Return flat [id => "name (type)"] for Filament Select options.
            // Use 'type' if available (from resource-type taxonomy), otherwise use post_title.
            $formatted = [];
            foreach ($units as $unit) {
                $id = $unit['id'] ?? $unit['ID'] ?? '';
                $name = $unit['name'] ?? 'Unknown';
                $type = $unit['type'] ?? '';
                $title = $unit['post_title'] ?? '';

                // Prefer type over post_title for the label suffix
                // Only add suffix if it's different from name to avoid duplicates like "Moon (Moon)"
                $suffix = $type ? $type : $title;
                $label = $suffix && strtolower($suffix) !== strtolower($name)
                    ? "{$name} ({$suffix})"
                    : $name;

                $formatted[$id] = $label;
            }

            Log::info('Multipass: Returning formatted units', [
                'count' => count($formatted),
                'units' => $formatted,
            ]);

            return $formatted;
        } catch (\Exception $e) {
            Log::error('Multipass: Exception fetching units', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
