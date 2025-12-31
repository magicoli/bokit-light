# AdminRegistry - Usage Examples

## Current Status
**DRAFT - Not yet fully implemented**

## Purpose
Unified registry for all admin menu items and routes, inspired by WordPress `add_menu_page()`.

## Use Cases

### 1. Models with AdminResourceTrait (Auto-registered)
```php
class Booking extends Model
{
    use AdminResourceTrait;
    // That's it! Routes and menu auto-registered
}
```

### 2. Custom Page for a Model
```php
// In AppServiceProvider::boot() or BookingServiceProvider::boot()
use App\Services\AdminRegistry;

AdminRegistry::registerPage([
    'id' => 'bookings-calendar',
    'label' => __('admin.calendar'),
    'parent' => 'bookings',  // Makes it a child of Bookings menu
    'icon' => 'calendar',
    'order' => 15,  // Appears between List (10) and Add (20)
    'permission' => fn() => user_can('manage', Booking::class),
    'view' => 'admin.bookings.calendar',
    'route_path' => '/bookings/calendar',
    'route_name' => 'bookings.calendar',
]);
```

Result in menu:
```
Bookings
  ├─ List
  ├─ Calendar  ← New custom page
  ├─ Add
  └─ Settings
```

### 3. Standalone Admin Page
```php
// System Health page
AdminRegistry::registerPage([
    'id' => 'system-health',
    'label' => __('admin.system_health'),
    'icon' => 'heart-pulse',
    'order' => 999,  // At the end of menu
    'permission' => fn() => user_can('manage', User::class),
    'view' => 'admin.health.index',
    'route_path' => '/health',
    'route_name' => 'health',
]);

// Or with a controller
AdminRegistry::registerPage([
    'id' => 'stripe-bridge',
    'label' => 'Stripe',
    'icon' => 'credit-card',
    'order' => 800,
    'permission' => fn() => user_can('manage', User::class),
    'controller' => [StripeController::class, 'dashboard'],
    'route_path' => '/bridges/stripe',
    'route_name' => 'bridges.stripe',
]);
```

### 4. Multi-action Page (GET + POST)
```php
AdminRegistry::registerPage([
    'id' => 'import-bookings',
    'label' => __('admin.import'),
    'parent' => 'bookings',
    'icon' => 'upload',
    'order' => 25,
    'permission' => fn() => user_can('manage', Booking::class),
    'controller' => [BookingController::class, 'importForm'],
    'route_path' => '/bookings/import',
    'route_name' => 'bookings.import',
    'route_methods' => ['GET'],
]);

// Separate POST route for processing
AdminRegistry::registerPage([
    'id' => 'import-bookings-process',
    'label' => null,  // Not shown in menu
    'controller' => [BookingController::class, 'importProcess'],
    'route_path' => '/bookings/import',
    'route_name' => 'bookings.import.process',
    'route_methods' => ['POST'],
]);
```

## Configuration Options

```php
[
    // Required
    'id' => 'unique-identifier',
    'label' => 'Display Label',
    'route_path' => '/path/relative/to/admin',
    'route_name' => 'name.relative.to.admin',
    
    // Optional
    'parent' => 'parent-menu-id',  // null for top-level
    'icon' => 'icon-name',
    'order' => 100,  // Sort order
    'permission' => fn() => user_can('action', Class::class),
    'view' => 'view.name',  // Either view OR controller required
    'controller' => [Class::class, 'method'],
    'route_methods' => ['GET'],  // Or ['GET', 'POST']
]
```

## Migration Path

### Current (Temporary)
- `routes/admin.php`: Auto-discovers models with trait
- `AdminMenuService`: Auto-discovers models with trait
- Duplication of discovery logic

### Future (When Implemented)
- `AppServiceProvider::boot()`: Call `AdminRegistry::discoverModels()`
- `routes/admin.php`: Call `AdminRegistry::registerRoutes()`
- `AdminMenuService`: Call `AdminRegistry::all()`
- Single source of truth for all admin items

## Benefits

1. **Consistency**: Same API for models, custom pages, plugins
2. **Flexibility**: Any class or script can add admin pages
3. **Decoupling**: Pages don't need to be tied to models
4. **Extensibility**: Plugins can register pages via service providers
5. **WordPress-like simplicity**: Easy to understand and use
