<?php

namespace Modules\Hbook;

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
}
