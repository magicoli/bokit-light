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
            foreach ($components as $component) {
                // Skip if not the sources section
                if (!isset($component['schema']) || !isset($component['schema'][0]) || !isset($component['schema'][0]['schema'])) {
                    continue;
                }
                
                $schema = $component['schema'][0]['schema'];
                $newSchema = [];
                
                foreach ($schema as $field) {
                    if (isset($field['name']) && $field['name'] === 'hbook_unit_id') {
                        // Replace with dynamic field
                        $newSchema[] = Select::make('hbook_unit_id')
                            ->label(__('unit.field.source_hbook_unit'))
                            ->options(fn () => $this->getHbookUnitsFromWordPress())
                            ->visible(fn (FormsGet|SchemaGet $get): bool => $get('type') === 'hbook')
                            ->columnSpan(1);
                    } else {
                        $newSchema[] = $field;
                    }
                }
                
                $component['schema'][0]['schema'] = $newSchema;
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
            $wpApi = app(\Modules\WpConnector\Services\WpApiService::class);
            
            // Get the current property from request or session
            $propertyId = request()->route('record') ?? session()->get('current_property_id');
            
            if ($propertyId) {
                $property = \App\Models\Property::find($propertyId);
                if ($property && !empty($property->options['wp_url'] ?? '')) {
                    return $wpApi->getHbookUnits($property->options);
                }
            }
            
            // Fallback: try to get from any configured property
            $properties = \App\Models\Property::whereNotNull('options->wp_url')->get();
            foreach ($properties as $property) {
                $units = $wpApi->getHbookUnits($property->options);
                if (!empty($units)) {
                    return $units;
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("HBook: Failed to fetch units", ['error' => $e->getMessage()]);
        }
        
        return [];
    }
}
