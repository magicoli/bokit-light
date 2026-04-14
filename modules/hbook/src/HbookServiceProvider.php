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
        $this->extendUnitForm();

        if ($this->app->runningInConsole()) {
            $this->commands([
                HbookImportCommand::class,
                MultipassImportCommand::class,
            ]);
        }
    }

    /**
     * Extend the unit form to add HBook unit mapping
     */
    protected function extendUnitForm(): void
    {
        UnitForm::extend(function (array $components): array {
            foreach ($components as &$component) {
                // Check if this is the sources repeater
                if (isset($component['schema']) && is_array($component['schema'])) {
                    foreach ($component['schema'] as &$section) {
                        if (isset($section['schema']) && is_array($section['schema'])) {
                            foreach ($section['schema'] as &$field) {
                                if (isset($field['name']) && $field['name'] === 'hbook_unit_id') {
                                    // Replace with dynamic field
                                    $field = Select::make('hbook_unit_id')
                                        ->label(__('unit.field.source_hbook_unit'))
                                        ->options(fn () => $this->getHbookUnitsFromWordPress())
                                        ->visible(fn (FormsGet|SchemaGet $get): bool => $get('type') === 'hbook')
                                        ->columnSpan(1);
                                }
                            }
                        }
                    }
                }
            }
            return $components;
        });
    }

    /**
     * Get HBook units from WordPress API
     */
    protected function getHbookUnitsFromWordPress(): array
    {
        try {
            // Get the current property from request
            $propertyId = request()->route('record');
            
            if ($propertyId) {
                $property = \App\Models\Property::find($propertyId);
                if ($property) {
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
            \Illuminate\Support\Facades\Log::error("HBook: Failed to fetch units", ['error' => $e->getMessage()]);
        }
        
        return [];
    }
}
