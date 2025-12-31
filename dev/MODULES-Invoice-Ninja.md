# Invoice Ninja Module Architecture

## Overview

The Invoice Ninja module provides accounting/invoicing integration as a **premium feature**. It can operate in two modes:
1. **Self-hosted**: Invoice Ninja installed locally in `public/third-party/invoice-ninja/`
2. **External**: Bridge to external Invoice Ninja instance

## Directory Structure

```
bokit-light/
├── modules/
│   └── invoice-ninja/                    ← Module code (git submodule or excluded)
│       ├── composer.json                 ← Module dependencies
│       ├── README.md                     ← Installation guide
│       ├── LICENSE.md                    ← Premium license
│       ├── src/
│       │   ├── InvoiceNinjaServiceProvider.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       ├── InvoiceNinjaController.php
│       │   │       └── InstallController.php
│       │   ├── Services/
│       │   │   ├── InvoiceNinjaBridge.php
│       │   │   ├── InvoiceNinjaClient.php
│       │   │   └── InvoiceNinjaInstaller.php
│       │   └── Console/
│       │       └── InstallCommand.php
│       ├── config/
│       │   └── invoice-ninja.php
│       ├── routes/
│       │   └── web.php
│       ├── resources/
│       │   └── views/
│       │       ├── install.blade.php
│       │       ├── settings.blade.php
│       │       └── redirect.blade.php
│       └── tests/
│           └── InvoiceNinjaBridgeTest.php
├── public/
│   ├── index.php                          ← Modified to detect /invoices/* routes
│   └── third-party/                       ← External apps (gitignored)
│       └── invoice-ninja/                 ← Invoice Ninja self-hosted (optional)
│           ├── index.php
│           ├── app/
│           └── ...
├── storage/
│   └── modules/
│       └── invoice-ninja/                 ← Module data
│           ├── database/                  ← IN database if SQLite
│           └── uploads/                   ← IN uploads
└── .distignore
│   modules/                               ← Excluded from Light releases
└── .gitignore
    public/third-party/                    ← Never committed
```

## Module Registration

**modules/invoice-ninja/src/InvoiceNinjaServiceProvider.php:**

```php
<?php

namespace Modules\InvoiceNinja;

use Illuminate\Support\ServiceProvider;
use Modules\InvoiceNinja\Services\InvoiceNinjaBridge;

class InvoiceNinjaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/invoice-ninja.php', 
            'invoice-ninja'
        );
    }

    public function boot()
    {
        // Only load if enabled
        if (!config('invoice-ninja.enabled', false)) {
            return;
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        
        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'invoice-ninja');
        
        // Publish config
        $this->publishes([
            __DIR__.'/../config/invoice-ninja.php' => config_path('invoice-ninja.php'),
        ], 'invoice-ninja-config');

        // Register with AdminRegistry
        InvoiceNinjaBridge::register();
    }
}
```

## Routing Strategy

**public/index.php (modification minimal):**

```php
<?php

use Illuminate\Http\Request;

// Delegation to third-party apps
if (preg_match('#^/invoices(/|$)#', $_SERVER['REQUEST_URI'])) {
    $invoiceNinjaPath = __DIR__ . '/third-party/invoice-ninja';
    
    if (is_dir($invoiceNinjaPath) && file_exists($invoiceNinjaPath . '/index.php')) {
        // Change working directory for Invoice Ninja
        chdir($invoiceNinjaPath);
        $_SERVER['SCRIPT_NAME'] = '/invoices/index.php';
        $_SERVER['SCRIPT_FILENAME'] = $invoiceNinjaPath . '/index.php';
        
        require $invoiceNinjaPath . '/index.php';
        exit;
    }
    
    // If not installed, fall through to Laravel (module will show install page)
}

// Standard Laravel bootstrap
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->handleRequest(Request::capture());
```

## Bridge Service

**modules/invoice-ninja/src/Services/InvoiceNinjaBridge.php:**

```php
<?php

namespace Modules\InvoiceNinja\Services;

use App\Services\AdminRegistry;
use Modules\InvoiceNinja\Http\Controllers\InvoiceNinjaController;

class InvoiceNinjaBridge
{
    public static function isInstalled(): bool
    {
        return is_dir(public_path('third-party/invoice-ninja'))
            && file_exists(public_path('third-party/invoice-ninja/index.php'));
    }
    
    public static function isExternal(): bool
    {
        return config('invoice-ninja.mode') === 'external';
    }
    
    public static function getUrl(): string
    {
        if (static::isExternal()) {
            return config('invoice-ninja.external_url');
        }
        return url('/invoices');
    }
    
    public static function register(): void
    {
        if (!static::isInstalled() && !static::isExternal()) {
            // Show installation page
            AdminRegistry::registerPage([
                'id' => 'invoice-ninja-install',
                'label' => __('invoice-ninja::admin.install'),
                'icon' => 'download',
                'order' => 999,
                'permission' => fn() => user_can('manage', \App\Models\User::class),
                'view' => 'invoice-ninja::install',
                'route_path' => '/modules/invoice-ninja/install',
                'route_name' => 'modules.invoice-ninja.install',
            ]);
            
            return;
        }
        
        // Main menu item
        AdminRegistry::registerPage([
            'id' => 'invoices',
            'label' => __('invoice-ninja::admin.invoices'),
            'icon' => 'receipt',
            'order' => 900,
            'permission' => fn() => user_can('view_invoices'),
            'controller' => [InvoiceNinjaController::class, 'redirect'],
            'route_path' => '/modules/invoice-ninja',
            'route_name' => 'modules.invoice-ninja.redirect',
        ]);
        
        // Settings page
        AdminRegistry::registerPage([
            'id' => 'invoice-ninja-settings',
            'label' => __('invoice-ninja::admin.settings'),
            'parent' => 'invoices',
            'order' => 999,
            'permission' => fn() => user_can('manage', \App\Models\User::class),
            'view' => 'invoice-ninja::settings',
            'route_path' => '/modules/invoice-ninja/settings',
            'route_name' => 'modules.invoice-ninja.settings',
        ]);
        
        // API endpoints for deep integration (optional)
        if (config('invoice-ninja.deep_integration', false)) {
            AdminRegistry::registerPage([
                'id' => 'invoice-ninja-create-from-booking',
                'label' => null,
                'controller' => [InvoiceNinjaController::class, 'createFromBooking'],
                'route_path' => '/modules/invoice-ninja/create/{booking}',
                'route_name' => 'modules.invoice-ninja.create',
                'route_methods' => ['POST'],
            ]);
        }
    }
}
```

## Configuration

**modules/invoice-ninja/config/invoice-ninja.php:**

```php
<?php

return [
    // Module enabled
    'enabled' => env('INVOICE_NINJA_ENABLED', false),
    
    // Mode: 'self-hosted' or 'external'
    'mode' => env('INVOICE_NINJA_MODE', 'self-hosted'),
    
    // External instance URL (if mode = external)
    'external_url' => env('INVOICE_NINJA_URL', 'https://invoicing.mycompany.com'),
    
    // API configuration
    'api_key' => env('INVOICE_NINJA_API_KEY'),
    'api_url' => env('INVOICE_NINJA_API_URL', url('/invoices/api')),
    
    // SSO configuration
    'sso_enabled' => env('INVOICE_NINJA_SSO', false),
    'sso_secret' => env('INVOICE_NINJA_SSO_SECRET'),
    
    // Deep integration features
    'deep_integration' => env('INVOICE_NINJA_DEEP_INTEGRATION', false),
    
    // Auto-create clients from bookings
    'auto_create_clients' => false,
    
    // Default tax rate
    'default_tax_rate' => 0,
];
```

## Installation Command

**modules/invoice-ninja/src/Console/InstallCommand.php:**

```php
<?php

namespace Modules\InvoiceNinja\Console;

use Illuminate\Console\Command;
use Modules\InvoiceNinja\Services\InvoiceNinjaInstaller;

class InstallCommand extends Command
{
    protected $signature = 'module:install-invoice-ninja {--version=v5.x}';
    protected $description = 'Install Invoice Ninja self-hosted';

    public function handle(InvoiceNinjaInstaller $installer)
    {
        $this->info('Installing Invoice Ninja...');
        
        $version = $this->option('version');
        
        $installer->download($version, function($progress) {
            $this->output->progressAdvance();
        });
        
        $this->info('Extracting...');
        $installer->extract();
        
        $this->info('Configuring...');
        $installer->configure();
        
        $this->info('Running migrations...');
        $installer->migrate();
        
        $this->info('Invoice Ninja installed successfully!');
        $this->info('Access it at: ' . url('/invoices'));
    }
}
```

## Modes of Operation

### Mode 1: Self-Hosted (Default)
```env
INVOICE_NINJA_ENABLED=true
INVOICE_NINJA_MODE=self-hosted
INVOICE_NINJA_API_KEY=generated-during-install
```

### Mode 2: External Instance
```env
INVOICE_NINJA_ENABLED=true
INVOICE_NINJA_MODE=external
INVOICE_NINJA_URL=https://invoicing.mycompany.com
INVOICE_NINJA_API_KEY=your-api-key
```

## Integration Levels

### Level 1: Basic (Link Only)
- Menu item redirects to Invoice Ninja
- No data exchange
- Separate auth

### Level 2: SSO
- Auto-login via token
- Single authentication
- Still separate apps

### Level 3: Deep Integration
- Create invoices from bookings
- Sync clients from properties
- Payment status in dashboard
- Requires API client

## Distribution

### Bokit Light (Free)
- Module **excluded** from repository
- `.gitignore` includes `modules/invoice-ninja/`
- No invoice functionality

### Bokit Pro (Premium)
- Module **included** via git submodule or direct inclusion
- Full integration capabilities
- License validation required

## .gitignore Rules

```gitignore
# Third-party apps (never committed)
/public/third-party/

# Premium modules (excluded in Light version)
/modules/invoice-ninja/

# Module storage
/storage/modules/*/
!storage/modules/.gitkeep
```

## Benefits of This Architecture

✅ **Clean separation** - All IN code in one module folder
✅ **Easy exclusion** - Simply don't include the module in Light
✅ **Self-contained** - Module has its own dependencies, config, tests
✅ **Flexible deployment** - Self-hosted OR external
✅ **PWA compatible** - Same origin, works offline
✅ **No domain proliferation** - Single bokit.click domain
✅ **Standard Laravel patterns** - Service provider, routes, views
✅ **AdminRegistry integration** - Menu items auto-registered
✅ **Optional deep integration** - Start simple, add features later

## Next Steps

When you're ready to implement:

1. **Create module structure** - `modules/invoice-ninja/` skeleton
2. **Implement routing** - Modify `public/index.php` for delegation
3. **Build installer** - Download/extract/configure IN
4. **Create bridge** - Basic menu items and redirect
5. **Add SSO** (optional) - Token-based authentication
6. **Deep integration** (optional) - API client for booking→invoice

Want me to create the basic module skeleton now, or wait until you actually need this feature?
