<?php

namespace Modules\Multipass;

use App\Filament\Resources\Units\Schemas\UnitForm;
use Illuminate\Support\ServiceProvider;

class MultipassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->extendUnitForm();
    }

    /**
     * Extend the unit form to add Multipass unit mapping
     */
    protected function extendUnitForm(): void
    {
        UnitForm::extend(function (array $components): array {
            foreach ($components as &$component) {
                if (isset($component['schema'][0]['schema'])) {
                    // Find the multipass_unit_id field and populate its options
                    foreach ($component['schema'][0]['schema'] as &$field) {
                        if (isset($field['name']) && $field['name'] === 'multipass_unit_id') {
                            // TODO: Replace with actual WordPress API call
                            $field['options'] = [
                                'mp-1' => 'Moon',
                                'mp-2' => 'Sun',
                                'mp-3' => 'Zandoli',
                                'mp-4' => 'Violeta',
                                'mp-5' => 'Zetoil',
                            ];
                        }
                    }
                }
            }
            return $components;
        });
    }
}
