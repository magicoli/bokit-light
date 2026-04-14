<?php

namespace Modules\Hbook;

use App\Filament\Resources\Units\Schemas\UnitForm;
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
                if (isset($component['schema'][0]['schema'])) {
                    // Find the hbook_unit_id field and populate its options
                    foreach ($component['schema'][0]['schema'] as &$field) {
                        if (isset($field['name']) && $field['name'] === 'hbook_unit_id') {
                            // TODO: Replace with actual WordPress API call
                            $field['options'] = [
                                'hbook-1' => 'Moon',
                                'hbook-2' => 'Sun',
                                'hbook-3' => 'Zandoli',
                                'hbook-4' => 'Violeta',
                                'hbook-5' => 'Zetoil',
                            ];
                        }
                    }
                }
            }
            return $components;
        });
    }
}
