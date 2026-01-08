# Bokit Light - ROADMAP

This is a long road. Make sure to always **process one step at a time**, to avoid drowning the user with too much changes at once, and allow efficient **verification and validation** of each step before proceeding to the next one.

## Most critical for basic functional deployment

The mission is to be functional  as soon as possible, with the minimum features required to achieve this goal. This includes:

- [x] Calendar, in sync with Channel Manager (at least with iCal)
- [ ] Sync should not not override local modifications
    - [ ] Save pre-processed sync data from separately, meaning data will be stored 3 times: the local data (as current, direct model attributes), the current raw sync data (currently in raw_data), and the pre_processed sync data. raw and pre-processed data must be stored in a subarray per source (currently ical only)
    - [ ] Update styles to show first-level fields label on the left of the value with fixed size, instead of above, and the diff with sync after the value, keeping description and errors below
    - [ ] Only first source is considered to evaluate if there is a difference between local data and sync data
- [ ] Sync Booking details (requires some API integration)
    - [x] status, name, phone, mail address, guests, notes
    - [ ] adults, children
    - [ ] price, paid, balance
- [ ] Actions column with inline links 
    - [x] status, view, edit
    - [ ] Direct link to the OTA booking edit/management page, fallback to CM booking page 
        - [x] Airbnb
        - [ ] booking.com
        - [ ] beds24
- [ ] Restore filter by units in Bookings list
- [ ] Same booking details popup in Calendar and admin List views
- [ ] Add OTA icon to booking block in calendar (same icon, but not the same treatment as in admin list: only display actual OTA, not cm, and display even if no management action link)
- [ ] Add notes to booking (not overridden by CM sync)
- [ ] Edit booking name and contacts (not overridden by CM sync)
- [ ] Create manual booking
- [ ] Export booking ics
- [ ] Export booking contacts as webcal (anticipate future Mailcow/Google/NextCloud address book integration)

## Booking API integration

**Important**: booking details might require some CM/OTA API integration. However, full API integration is not a priority for the initial release, make sure to take a **minimal API integration approach**, focusing only on receiving the missing data.

**Major Requirement, critical**:

All API integrations are optional modules. They are included in Pro version, not in light version
-> they must be implemented as modules in modules/ folder
-> they cannot be reffered directly by the main app code, the modules can be deleted at any time without affecting the main app functionment.
-> the main app must not depend on any module, it must be able to run without any module
-> the main app loads the available modules, and the modules add their functionalties to the main classes/traits/services/methods/etc.

**For The OTA API**, to implement more complete booking sync:
- currently we only focus on beds24 api
- currently we only need to set up one site-wide account for api keys. Eventually, each owner/property/unit could have their own api keys, but we do not care about it for now.
- each unit **needs** it's own mapping config, though
- must add a sync method option to the current iCal method, with method-specific parameters (for iCal it's currently only an url, for OTA it will require mapping details)
- current required settings pages updates (to be added added by beds24 module)
  - [x] General settings (/admin/settings):
    - [x] The settings page should be adapted to properly use Form class
    - [ ] Beds24 section with API id/keys/secrets
  - ~~Per-model settings (/admin/<bookings|properties|...>}/settings): not needed at this stage~~
  - [ ] Activate universal actions view and edit for ModelConfigTrait
  - [ ] -> Unit Edit page (/admin/units/{id}/edit):
    - [ ] Implement basic edit page with $fillable defined by model
    - [ ] Verify that current iCal settings (as seen in former /{property}/{unit}/edit page) are properly displayed and editable in the new edit page
    - [ ] Rewrite source section to allow additional source types nd parameters (iCal url, APIs will need other kind of parameters, add dumb API type and parameters for testing)
    - [ ] Implement actual Beds24 API type through basic beds24 module, with Beds24-specific options (mapping details instead of iCal url)

## Post-Deployment Enhancements

These improvements are not critical for basic functionality but will improve maintainability and developer experience:

### List Display & Actions
- [x] Flexible list columns configuration per model
- [ ] **Complete model-level actions configuration**
  - Define default actions in ModelConfigTrait (status, edit, view)
  - Allow models to add custom actions with `$this->addAction($array)`
  - Support for action icons, URLs, titles, and targets

### Status Management System
- [ ] **Unified status handling as objects**
  - Currently: status managed by ModelConfigTrait + scattered filters/checks
  - Goal: Single consistent interface across the application
  - Proposed syntax:
    - `$status` → returns slug (string)
    - `$status->name()` → returns localized status name
    - `$status->color()` → returns CSS color class
    - `$status->icon()` → returns complete HTML icon (via helper `icon()`)
    - Extensible for future needs (badge, tooltip, etc.)

### Icon Management Optimization
- [ ] **Efficient icon build process**
  - Problem: Full SVG libraries (Font Awesome Pro, etc.) too heavy for repository
  - Solution: Build process that copies only used icons to `public/`
  - Keep SVG format for easy custom icon additions
  - Helper `icon()` generates relative path to `public/svg/`
  - Reduces release package size significantly

### Build & Release Optimization
- [ ] **Verify resources/ directory usage**
  - Ensure `resources/` only needed for build, not in production release
  - Production should only need `public/` for compiled assets
  - Document which directories are build-time vs runtime dependencies

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
public static function setConfig(): array
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
