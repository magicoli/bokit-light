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
            // Find the sources repeater and extend it
            foreach ($components as $index => $component) {
                if (isset($component['schema'][0]['schema'])) {
                    $schema = $component['schema'][0]['schema'];
                    
                    // Find and update the hbook_unit_id field
                    foreach ($schema as $fieldIndex => $field) {
                        if (isset($field['name']) && $field['name'] === 'hbook_unit_id') {
                            // Create a new field with dynamic options from WordPress
                            $schema[$fieldIndex] = Select::make('hbook_unit_id')
                                ->label(__('unit.field.source_hbook_unit'))
                                ->options(fn () => $this->getHbookUnitsFromWordPress())
                                ->visible(fn (FormsGet|SchemaGet $get): bool => $get('type') === 'hbook')
                                ->columnSpan(1);
                        }
                    }
                    
                    $components[$index]['schema'][0]['schema'] = $schema;
                }
            }
            return $components;
        });
    }

    /**
     * Get HBook units from WordPress API
     * TODO: Implement actual WordPress API call via WP Connector module
     */
    protected function getHbookUnitsFromWordPress(): array
    {
        // This should call the WP Connector module to fetch units from WordPress
        // For now, return empty array - the actual implementation will be added later
        return [];
    }
}
