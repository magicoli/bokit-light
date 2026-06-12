<?php

namespace Modules\Hbook;

use App\Models\Unit;
use App\Services\SyncRegistry;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\Hbook\Commands\HbookImportCommand;
use Modules\Hbook\Services\HbookConnector;
use Modules\WpConnector\Services\WpConnectorService;

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
            ]);
        }

        $registry = $this->app->make(SyncRegistry::class);
        $registry->register(new HbookConnector);
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
        Log::info('HBook: getHbookUnitsFromWordPress() called', ['unit_id' => $unitId]);

        try {
            // Get the current unit's property from request or parameter
            if (! $unitId) {
                // Try to get from request first
                $unitId = request()->route('record');
                if ($unitId) {
                    Log::info('HBook: Using unit ID from request', ['unit_id' => $unitId]);
                } else {
                    // Fallback: try to get the first unit from the database
                    // This is a temporary solution until we find the right way to get the current unit ID
                    $firstUnit = Unit::first();
                    if ($firstUnit) {
                        $unitId = $firstUnit->id;
                        Log::info('HBook: Using fallback unit ID from first unit in database', ['unit_id' => $unitId]);
                    }
                }
            }

            if (! $unitId) {
                Log::warning('HBook: No unit ID available');

                return [];
            }

            $unit = Unit::find($unitId);

            if (! $unit) {
                Log::warning('HBook: Unit not found', ['unit_id' => $unitId]);

                return [];
            }

            $property = $unit->property;

            if (! $property) {
                Log::warning('HBook: Unit has no property', ['unit_id' => $unitId]);

                return [];
            }

            $wpConnector = new WpConnectorService($property);

            if (! $wpConnector->isConfigured()) {
                Log::warning('HBook: WP Connector not configured for property', [
                    'property' => $property->id,
                    'unit' => $unit->id,
                ]);

                return [];
            }

            $cacheKey = "hbook_units_property_{$property->id}";

            return Cache::remember($cacheKey, 300, function () use ($wpConnector, $property, $unit) {
                Log::info('HBook: Fetching all units from WordPress site', [
                    'property' => $property->id,
                    'unit' => $unit->id,
                    'wp_url' => $property->options['wp_url'],
                ]);

                $response = $wpConnector->get('/wp-json/bokit/v1/hbook-units');

                Log::info('HBook: WordPress API response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if (! $response->successful()) {
                    Log::warning('HBook: WordPress API returned non-success status', [
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                $units = $response->json('units', []);
                Log::info('HBook: Parsed units from WordPress', ['count' => count($units)]);

                // Return flat [id => "name (post_title)"] for Filament Select options.
                $formatted = [];
                foreach ($units as $unit) {
                    $id = $unit['id'] ?? $unit['ID'] ?? '';
                    $name = $unit['name'] ?? 'Unknown';
                    $title = $unit['post_title'] ?? '';
                    $label = $title ? "{$name} ({$title})" : $name;
                    $formatted[$id] = $label;
                }

                Log::info('HBook: Returning formatted units', [
                    'count' => count($formatted),
                ]);

                return $formatted;
            });
        } catch (\Exception $e) {
            Log::error('HBook: Exception fetching units', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
