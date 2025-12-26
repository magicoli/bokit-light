# Bokit Modules Architecture

## Overview

Bokit follows a modular architecture where optional features are packaged as self-contained modules. This enables:
- Separate distribution and licensing
- Easy enable/disable functionality
- Clear separation between free and premium features
- Simplified maintenance and testing

## Module Types

### Core (Included, Free)
**Location**: Core Laravel application
- Calendar display and management
- Property/Unit management
- User authentication and permissions
- Basic booking CRUD
- iCal import/export (free self-host, low-cost SaaS)

### Premium Modules (Optional, Paid)
**Location**: `modules/` directory
- Channel Manager API integrations (Beds24, Lodgify, etc.)
- Advanced invoicing/accounting integrations
- Payment processing (Stripe, PayPal)
- Revenue management and analytics
- Advanced reporting

### Integration Modules (Optional, Free or Paid)
**Location**: Separate repositories
- WordPress connector plugin
- Drupal module
- Standalone embeddable widget
- Mobile app bridges

## Module Structure

Each module is self-contained with its own:

```
modules/{module-name}/
├── composer.json              # Dependencies
├── README.md                  # Installation and usage
├── LICENSE.md                 # Licensing terms
├── src/
│   ├── ModuleServiceProvider.php  # Laravel service provider
│   ├── Http/
│   │   ├── Controllers/       # Module controllers
│   │   └── Middleware/        # Module middleware
│   ├── Models/                # Module models
│   ├── Services/              # Business logic
│   └── Console/               # Artisan commands
├── config/
│   └── {module-name}.php      # Module configuration
├── database/
│   └── migrations/            # Module migrations
├── routes/
│   ├── web.php               # Web routes
│   └── api.php               # API routes
├── resources/
│   ├── views/                # Blade templates
│   ├── lang/                 # Translations
│   ├── css/                  # Module styles
│   └── js/                   # Module scripts
└── tests/                    # Module tests
```

## Module Discovery & Loading

### 1. Auto-Discovery
Modules are automatically discovered from the `modules/` directory during boot.

```php
// bootstrap/providers.php or AppServiceProvider
foreach (glob(base_path('modules/*/src/*ServiceProvider.php')) as $provider) {
    $class = str_replace(
        [base_path('modules/'), '/src/', '.php', '/'],
        ['Modules\\', '\\', '', '\\'],
        $provider
    );
    if (class_exists($class)) {
        $this->app->register($class);
    }
}
```

### 2. Module Registration
Each module has a `ModuleServiceProvider` that registers its components:

```php
namespace Modules\Beds24\src;

use Illuminate\Support\ServiceProvider;

class Beds24ServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/beds24.php', 'beds24');
    }

    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        
        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'beds24');
        
        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'beds24');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // Publish assets
        $this->publishes([
            __DIR__.'/../resources/css' => public_path('modules/beds24/css'),
            __DIR__.'/../resources/js' => public_path('modules/beds24/js'),
        ], 'beds24-assets');
        
        // Publish config
        $this->publishes([
            __DIR__.'/../config/beds24.php' => config_path('beds24.php'),
        ], 'beds24-config');
    }
}
```

## Module Activation

### Database-Driven Activation
Modules can be enabled/disabled via database configuration:

```sql
-- modules table
CREATE TABLE modules (
    id INTEGER PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    enabled BOOLEAN DEFAULT 0,
    version VARCHAR(50),
    config JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Options-Based Configuration
Use `Options::get('modules.enabled')` for runtime checks:

```php
// Check if module is enabled
if (Options::get('modules.beds24.enabled', false)) {
    // Load Beds24 functionality
}
```

### Admin UI
Module management interface in admin panel:
- List installed modules
- Enable/disable toggle
- Configuration form
- License key validation (for premium modules)
- Version information

## Example Modules

### 1. Beds24 API Sync (Premium)

**Purpose**: Bidirectional sync with Beds24 PMS via API

**Location**: `modules/beds24/`

**Features**:
- Import bookings with full details (price, guests, notes)
- Export bookings to Beds24
- Real-time webhook support
- Conflict resolution
- Custom field mapping

**Pricing**: Subscription-based (monthly/yearly)

**Dependencies**:
- Guzzle HTTP client
- Laravel queue system

**Configuration**:
```php
// config/beds24.php
return [
    'api_key' => env('BEDS24_API_KEY'),
    'property_id' => env('BEDS24_PROPERTY_ID'),
    'sync_interval' => 60, // minutes
    'webhook_secret' => env('BEDS24_WEBHOOK_SECRET'),
];
```

---

### 2. Lodgify Integration (Premium)

**Purpose**: Sync with Lodgify channel manager

**Location**: `modules/lodgify/`

**Features**: Similar to Beds24 but for Lodgify API

**Pricing**: Subscription-based

---

### 3. Invoice Ninja Integration (Premium)

**Purpose**: Connect with Invoice Ninja for billing/invoicing

**Location**: `modules/invoice-ninja/`

**Features**:
- Generate invoices from bookings
- Track payments
- Send automated reminders
- Tax compliance

**Pricing**: One-time purchase or subscription

---

### 4. Stripe Payment (Premium)

**Purpose**: Accept online payments via Stripe

**Location**: `modules/stripe-payment/`

**Features**:
- Deposit collection
- Balance payments
- Refund processing
- Payment intent management

**Pricing**: Transaction fee or subscription

---

### 5. WordPress Connector (Free)

**Purpose**: Embed Bokit calendar in WordPress sites

**Location**: Separate repository (`bokit-wordpress/`)

**Distribution**: WordPress Plugin Directory

**Features**:
- Shortcode support `[bokit-calendar]`
- Widget for sidebars
- Admin settings page
- API key management

**Technical Approach**:
- WordPress plugin that calls Bokit API
- Optional: iframe embed
- Optional: direct integration if Bokit installed on same server

---

## Module Development Guidelines

### 1. Namespace Convention
```php
namespace Modules\{ModuleName}\{Subfolder};
```

Example: `Modules\Beds24\Services\SyncService`

### 2. Route Prefixes
```php
// routes/web.php
Route::prefix('beds24')->name('beds24.')->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
});
```

### 3. View Namespacing
```blade
@extends('beds24::layouts.admin')

@section('content')
    <!-- Module content -->
@endsection
```

### 4. Configuration
- Use `config('beds24.api_key')` for module settings
- Use `Options::get('beds24.sync_interval')` for user-configurable options
- Publish config files for customization

### 5. Migrations
- Prefix tables with module name: `beds24_bookings`, `lodgify_properties`
- Use proper foreign keys to core tables
- Include rollback methods

### 6. Assets
- Compile module assets separately: `npm run build:beds24`
- Publish to `public/modules/{module-name}/`
- Use versioned asset URLs for cache busting

### 7. Testing
- Each module has its own test suite
- Integration tests with core application
- Mock external API calls

### 8. Documentation
- README.md with installation steps
- API documentation if applicable
- Configuration examples
- Troubleshooting guide

### 9. Licensing
- Premium modules include license validation
- License checking via central license server
- Grace period for expired licenses
- Clear licensing terms in LICENSE.md

## Module Lifecycle

### Installation
```bash
# Via Composer (for premium modules)
composer require bokit/module-beds24

# Via Artisan
php artisan module:install beds24

# Manual (development)
git clone modules/beds24
php artisan migrate
php artisan module:enable beds24
```

### Update
```bash
composer update bokit/module-beds24
php artisan migrate
php artisan module:publish beds24 --force
```

### Removal
```bash
php artisan module:disable beds24
php artisan module:uninstall beds24  # Removes migrations
composer remove bokit/module-beds24
```

## Security Considerations

### 1. API Key Storage
- Never commit API keys
- Use environment variables
- Encrypt sensitive data in database
- Provide key rotation mechanism

### 2. Webhook Validation
- Verify webhook signatures
- Rate limiting
- IP whitelist where applicable
- Log all webhook events

### 3. Permission Checks
- Module routes respect core permissions
- Additional module-specific permissions
- Audit log for sensitive operations

### 4. Data Isolation
- Modules cannot access other modules' tables directly
- Use events/listeners for inter-module communication
- Clear API contracts

## Performance Optimization

### 1. Lazy Loading
- Load modules only when needed
- Conditional service provider registration
- Defer heavy initialization

### 2. Caching
- Cache module configuration
- Cache API responses where appropriate
- Module-specific cache tags

### 3. Queue Usage
- API sync operations in background jobs
- Batch processing for bulk imports
- Failed job handling and retry logic

## Distribution & Monetization

### Free Modules (iCal Sync)
- Included in core repository
- Self-hosting: free
- SaaS hosting: low monthly fee ($5-10/month)

### Premium Modules (Channel Manager APIs)
- Separate repositories (private)
- Subscription-based licensing
- Annual or monthly billing
- License validation via central server

### Integration Modules (WordPress, Drupal)
- Separate public repositories
- Free or freemium model
- Link back to main Bokit site
- Can be monetized via premium features

## Future Considerations

### Module Marketplace
- Central repository for approved modules
- Third-party module submission
- Review and approval process
- Revenue sharing for partner modules

### Plugin API
- Documented hooks and filters
- Event system for module communication
- Versioned API contracts
- Deprecation policies

### Multi-Tenancy
- Modules per tenant configuration
- Tenant-specific module activation
- License validation per tenant
- Resource isolation

---

## Implementation Checklist

For each new module:
- [ ] Create module directory structure
- [ ] Implement ServiceProvider
- [ ] Add configuration file
- [ ] Create migrations
- [ ] Add routes (web + API)
- [ ] Create views (if UI needed)
- [ ] Add translations
- [ ] Write tests
- [ ] Document installation
- [ ] Add to module registry
- [ ] Implement license validation (premium only)
- [ ] Create upgrade path from previous versions
