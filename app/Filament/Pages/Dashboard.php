<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;

class Dashboard extends BaseDashboard
{
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * Widget grid: 1 column on mobile, 2 on tablet, 4 on large screens.
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }
}
