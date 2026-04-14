<?php

namespace Modules\Multipass;

use App\Filament\Resources\Units\Schemas\UnitForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Get as FormsGet;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Illuminate\Support\ServiceProvider;

class MultipassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // No extension needed - options are set directly in UnitForm
    }

    /**
     * Get Multipass units from WordPress API
     */
    public static function getMultipassUnitsFromWordPress(): array
    {
        try {
            // Get the current property from request
            $propertyId = request()->route('record');
            
            if ($propertyId) {
                $property = \App\Models\Property::find($propertyId);
                if ($property) {
                    $wpConnector = new \Modules\WpConnector\Services\WpConnectorService($property);
                    if ($wpConnector->isConfigured()) {
                        $response = $wpConnector->get('/wp-json/bokit/v1/multipass-units');
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
            }
            
            // Fallback: try to get from any configured property
            $properties = \App\Models\Property::whereNotNull('options->wp_url')->get();
            foreach ($properties as $property) {
                $wpConnector = new \Modules\WpConnector\Services\WpConnectorService($property);
                if ($wpConnector->isConfigured()) {
                    $response = $wpConnector->get('/wp-json/bokit/v1/multipass-units');
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
            \Illuminate\Support\Facades\Log::error("Multipass: Failed to fetch units", ['error' => $e->getMessage()]);
        }
        
        return [];
    }
}
