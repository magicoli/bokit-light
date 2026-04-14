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
     */
    public static function getHbookUnitsFromWordPress(): array
    {
        \Illuminate\Support\Facades\Log::info("HBook: getHbookUnitsFromWordPress() called");
        
        try {
            // Get the current property from request
            $propertyId = request()->route('record');
            
            if ($propertyId) {
                $property = \App\Models\Property::find($propertyId);
                if ($property) {
                    $wpConnector = new \Modules\WpConnector\Services\WpConnectorService($property);
                    if ($wpConnector->isConfigured()) {
                        \Illuminate\Support\Facades\Log::info("HBook: Fetching units from WordPress", [
                            'property' => $property->id,
                            'url' => $property->options['wp_url'],
                        ]);
                        
                        $response = $wpConnector->get('/wp-json/hbook/v1/units');
                        
                        \Illuminate\Support\Facades\Log::info("HBook: WordPress API response", [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                        
                        if ($response->successful()) {
                            $units = $response->json('units', []);
                            \Illuminate\Support\Facades\Log::info("HBook: Parsed units", ['units' => $units]);
                            
                            $formatted = array_map(function ($unit) {
                                return [
                                    'id' => $unit['id'] ?? $unit['ID'] ?? '',
                                    'name' => $unit['name'] ?? $unit['post_title'] ?? 'Unknown',
                                ];
                            }, $units);
                            
                            \Illuminate\Support\Facades\Log::info("HBook: Formatted units", ['formatted' => $formatted]);
                            return $formatted;
                        } else {
                            \Illuminate\Support\Facades\Log::warning("HBook: WordPress API returned non-success status", [
                                'status' => $response->status(),
                            ]);
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning("HBook: WP Connector not configured for property", [
                            'property' => $property->id,
                        ]);
                    }
                }
            }
            
            // Fallback: try to get from any configured property
            $properties = \App\Models\Property::whereNotNull('options->wp_url')->get();
            foreach ($properties as $property) {
                $wpConnector = new \Modules\WpConnector\Services\WpConnectorService($property);
                if ($wpConnector->isConfigured()) {
                    $response = $wpConnector->get('/wp-json/hbook/v1/units');
                    if ($response->successful()) {
                        $units = $response->json('units', []);
                        return array_map(function ($unit) {
                            return [
                                'id' => $unit['id'] ?? $unit['ID'] ?? '',
                                'name' => $unit['name'] ?? $unit['post_title'] ?? 'Unknown',
                            ];
                        }, $units);
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("HBook: Failed to fetch units", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        \Illuminate\Support\Facades\Log::info("HBook: No units found, returning empty array");
        return [];
    }
}
