# Bokit Light - ROADMAP

Every roadmap item moved to the ticket tracker on 2026-07-27 — see `/admin/tickets`, or
`GET /api/tickets`. One list for tasks, bugs and features alike; nothing planned is tracked here
any more.

What remains below is reference material: the module architecture requirement, which is a hard
rule, and the design sketches the future features were described with. It belongs in
DEVELOPERS.md and is tracked as such in the tracker.

## Module architecture — critical requirement

All API integrations are optional modules. They are included in the Pro version, not in the light
version.

- they must be implemented as modules in the `modules/` folder
- they cannot be referred to directly by the main app code; a module can be deleted at any time
  without affecting how the main app runs
- the main app must not depend on any module, it must run without any of them
- the main app loads the available modules, and the modules add their features to the main
  classes, traits, services and methods

Minimal API integration approach: booking details may need CM/OTA API access, but full API
integration is not a priority — take only what is needed to fill in the missing data.

For the OTA API: Beds24 is the only target for now; one site-wide account for API keys is enough
(per owner, property or unit keys can come later), but each unit **needs** its own mapping config.

## Reference: route sketches

```php
// Frontend public views
Route::get('/{property:slug}', [PropertyController::class, 'show']);  // Custom
Route::get('/{property:slug}/{unit:slug}', [UnitController::class, 'show']);  // Custom
Route::get('/user/{user:slug}', [UserController::class, 'show']);  // Default pattern
Route::get('/booking/{booking}', [BookingController::class, 'show']);  // ID fallback

// Print views
Route::get('/legacy-admin/bookings/{id}/print', [AdminResourceController::class, 'print']);
Route::get('/legacy-admin/properties/{id}/print', [AdminResourceController::class, 'print']);

// CMS Pages
Route::get('/{page:slug}', [PageController::class, 'show']);  // Catch-all for pages
```

## Reference: model configuration sketch

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

## Notes

- Keep KISS principle: implement features when needed, not preemptively
- Process one step at a time, verified and validated before moving to the next
- Frontend views are LOW priority (most properties managed externally)
- Print views useful for invoices, contracts, reports
- CMS Page model: minimal viable product only
