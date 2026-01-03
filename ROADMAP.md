# Bokit Light - ROADMAP

## Most critical for basic functional deployment

The mission is to be functional  as soon as possible, with the minimum features required to achieve this goal. This includes:

- [x] Calendar, in sync with Channel Manager (at least with iCal)
- [ ] Booking details (name, phone, mail address, guests, adults, children, notes, price, paid, balance, status): partially implemented
- [ ] Same booking details popup in Calendar and admin List views
- [ ] Add notes to booking (not overridden by CM sync)
- [ ] Edit booking name and contacts (not overridden by CM sync)
- [ ] In actions links: Direct link to the OTA booking edit/management page, fallback to CM booking page (implemented for Airbnb, to implement for booking.com and beds24)
- [ ] Create manual booking
- [ ] Export booking ics
- [ ] Export booking contacts as webcal (anticipate future Mailcow/Google/NextCloud address book integration)

**Important**: booking details might require some CM/OTA API integration. However, full API integration is not a priority for the initial release, make sure to take a **minimal API integration approach**, focusing only on receiving the missing data.

## AdminResourceTrait - Future Features

### Frontend Public Views
- [ ] **Public display routes** for selected models (disabled by default)
  - Property & Unit: Custom slug routes `/<property-slug>` and `/<property-slug>/<unit-slug>`
  - Other models: Default pattern `/<model-slug>/<object-slug>` or `/<model-slug>/<id>` if no slug
  - **Slug generation rules** to be defined per model
  - **Model property** to control which features are enabled (list, show, edit, settings, frontend_view, categories, etc.)
  - Default features in trait: `['list', 'show', 'edit', 'settings']`
  
### Print Views
- [ ] **Print-optimized views** `/{resource}/{id}/print`
  - Default CSS: Hide menus, sidebars, navigation
  - Optional: Custom print templates per model
  - PDF generation support

### Basic CMS
- [ ] **Page model** for basic content management
  - Simple page creation/editing
  - No sophisticated features (WordPress/Drupal better for that)
  - Just enough for landing pages, About, Terms, etc.
  - Slug-based routing
  - Basic WYSIWYG editor

### Route Examples (Future)

```php
// Frontend public views
Route::get('/{property:slug}', [PropertyController::class, 'show']);  // Custom
Route::get('/{property:slug}/{unit:slug}', [UnitController::class, 'show']);  // Custom
Route::get('/user/{user:slug}', [UserController::class, 'show']);  // Default pattern
Route::get('/booking/{booking}', [BookingController::class, 'show']);  // ID fallback

// Print views
Route::get('/admin/bookings/{id}/print', [AdminResourceController::class, 'print']);
Route::get('/admin/properties/{id}/print', [AdminResourceController::class, 'print']);

// CMS Pages
Route::get('/{page:slug}', [PageController::class, 'show']);  // Catch-all for pages
```

### Model Configuration (Future)

```php
// In Model using AdminResourceTrait
public static function adminConfig(): array
{
    return [
        'label' => __('admin.bookings'),
        'icon' => 'calendar',
        'routes' => ['list', 'show', 'add', 'edit', 'settings'],
        'order' => 10,
        
        // Future features
        'features' => [
            'frontend_view' => true,  // Enable public display
            'print_view' => true,     // Enable print template
            'categories' => false,    // Enable categorization
            'tags' => false,          // Enable tagging
        ],
        
        // Custom slug pattern (default: model-slug/object-slug)
        'public_route_pattern' => '/booking/{slug}',  // or null for default
        
        // Slug field (default: 'slug')
        'slug_field' => 'reference',  // e.g., for bookings use reference code
    ];
}
```

## Current Status (2025-12-31)

### ✅ Implemented
- AdminResourceTrait with auto-discovery
- Backend routes: index, list, create/store, show, edit/update/destroy, settings
- Menu auto-generation with collapse/expand
- Role-based body/menu classes
- Translations (EN/FR)
- Generic views with placeholders

### 🚧 In Progress
- DataList implementation for list views
- Form implementation for create/edit views
- Settings framework
- Validation in store/update

### 📋 Next Up (Priority Order)
1. Complete DataList and Form implementations
2. Implement show view with tabs/actions
3. Add slug generation system
4. Implement frontend public views
5. Add print views
6. Create Page model for CMS

## Notes

- Keep KISS principle: implement features when needed, not preemptively
- Frontend views are LOW priority (most properties managed externally)
- Print views useful for invoices, contracts, reports
- CMS Page model: minimal viable product only
