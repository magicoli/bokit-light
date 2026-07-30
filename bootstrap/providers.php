<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\BasePanelProvider::class,
    App\Providers\Filament\HasSharedPanelConfigPanelProvider::class,
    App\Providers\Filament\InstallPanelProvider::class,
    App\Providers\Filament\MainPanelProvider::class,
    App\Providers\Filament\NewpanelPanelProvider::class,
    App\Providers\Filament\ThirdOnePanelProvider::class,
    Modules\Beds24\Beds24ServiceProvider::class,
    Modules\Hbook\HbookServiceProvider::class,
    Modules\Multipass\MultipassServiceProvider::class,
    Modules\WpConnector\WpConnectorServiceProvider::class,
];
