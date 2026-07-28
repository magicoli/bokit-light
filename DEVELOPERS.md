# Bokit Developer Guide

## Overview

This document outlines the development principles, conventions, and workflows for contributing to Bokit. Whether you're working on the core application or developing modules, following these guidelines ensures consistency and maintainability.

## Core Principles

### Keep It Simple and Smart (KISS)
- Aim for functional results quickly without sacrificing future evolution
- Code must remain scalable while focusing only on current needs
- Avoid over-engineering or premature optimization
- Prefer standard Laravel patterns over custom solutions

### Consistency Over Cleverness
- Follow existing patterns in the codebase
- Use shared components (DataList, form helpers, etc.)
- Duplicated code is bad code - always refactor common logic
- Maintain uniform styling and naming conventions

### Laravel-First Approach
- Favor Laravel's built-in features over custom implementations
- Use Eloquent, not raw SQL (except for complex queries)
- Leverage Laravel's validation, authorization, and queue systems
- Follow Laravel best practices and conventions

## Project Structure

```
bokit-light/
├── app/
│   ├── Console/Commands/      # Artisan commands
│   ├── Filament/Resources/    # Filament admin panel resources
│   ├── Http/Controllers/      # Request handlers
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic
│   ├── Sync/                  # The synchronisation subsystem, entry points included
│   └── Support/               # Helpers (DataList, etc.)
├── database/
│   └── migrations/            # Database schema versions
├── lang/                      # Translations (en/, fr/)
├── modules/                   # Optional premium/integration modules
│   ├── beds24
│   ├── hbook
│   ├── ical                  # Not yet implemented, will end up here
│   ├── multipass
│   └── wp-connector
├── resources/
│   ├── css/                   # Modular stylesheets
│   ├── js/                    # JavaScript components
│   └── views/                 # Blade templates
├── routes/
│   ├── admin.php             # Admin zone routes
│   ├── api.php               # Token-authenticated JSON endpoints
│   ├── web.php               # Application routes
│   └── console.php           # Scheduler configuration
├── dev/                      # Development documentation
└── tmp/                      # Temporary files (gitignored)
```

## Development Workflow

### Environment Setup

See [INSTALLATION.md](INSTALLATION.md) for installing the app itself; what follows is only what
differs when working on it.

1. **Run the whole environment with one command**:
   ```bash
   composer run dev
   ```
   It starts the server, the queue listener, the log tailer and Vite together, and stops them
   together. Running `php artisan serve` or `npm run dev` on their own leaves half the environment
   missing — most visibly, assets stop being rebuilt.

   Vite rebuilds take a few seconds, during which the manifest is briefly absent and any page or
   test that renders a view fails. Wait for the rebuild before concluding anything about a change.

2. **Database migrations**:
   ```bash
   php artisan migrate
   ```
   `migrate:fresh` and `migrate:refresh` are deliberately blocked (see
   `app/Console/Commands/ProtectedMigrateCommand.php`): they drop everything, and the local
   database holds real bookings imported from live sources. To exercise a data migration, replay
   it against a copy rather than resetting.

### Making Changes

1. **Create Feature Branch** (if using Git flow):
   ```bash
   git checkout -b feat/rate-calculator
   ```

2. **Make Focused Changes**:
   - Edit only files related to your feature
   - Don't modify unrelated code
   - Keep commits atomic and logical

3. **Test Changes**:
   ```bash
   php artisan test --compact                    # the whole suite
   php artisan test --compact --filter=someTest  # while iterating
   ```
   `composer run dev` already rebuilds the assets; a data migration is exercised against a copy of
   a real database, never by resetting this one.

4. **Commit with Convention**:
   ```bash
   git add -A
   git commit -m "feat(rates): add calculator widget with grouping"
   ```

See [Commit Message Format](#commit-message-format) below.

## Code Conventions

### PHP/Laravel

**Model Conventions**:
```php
// Use Eloquent relationships
public function property() {
    return $this->belongsTo(Property::class);
}

// Use accessors/mutators for computed values
public function getNightsAttribute() {
    return $this->check_in->diffInDays($this->check_out);
}

// Use model events for side effects
protected static function booted() {
    static::updating(function ($rate) {
        // Sync parent base to children
    });
}
```

**Controller Conventions**:
```php
// Keep controllers thin
public function calculate(Request $request) {
    $validated = $request->validate([...]);
    $results = $this->calculatorService->calculate($validated);
    return view('rates', compact('results'));
}

// Use service classes for complex logic
$this->rateCalculatorService->calculateBookingPrice($booking);
```

**Service Classes**:
```php
// Business logic goes in services
namespace App\Services;

class RateCalculator {
    public function calculate($checkIn, $checkOut, $guests) {
        // Complex calculation logic
    }
}
```

**PHP formatting — use `mago format`, not Pint**:

Pint (php-cs-fixer) has, in rare cases, actually broken code: chained `->method()` calls and
ternaries combined with parentheses are known weak spots where it miscounts precedence, forcing
convoluted rewrites just to keep it from mangling otherwise-correct code (Oli, 2026-07-23). Its
style doesn't match this project's actual convention either — Zed's `format_on_save` runs
`mago format` — so anything Pint reformats gets undone the next time the file is saved in the
editor.

Install it globally, never as a project dependency:

```bash
composer global require carthage-software/mago
mago format app/Services/SyncEngine.php   # changed files only
```

**Never run Pint, Mago, or any other formatter across the whole project.** It rewrites dozens of
untouched files for futile formatting differences and drowns the real diff. `mago.toml` pins
`php-version` for exactly that reason: with no configuration Mago assumes the newest PHP it knows
and rewrites code to match.

### Configuration Management

**Never use constants** for configuration:
```php
// ❌ WRONG
const MAX_GUESTS = 12;

// ✅ RIGHT - Static app parameters
Config::set('booking.max_guests', 12);
$maxGuests = Config::get('booking.max_guests');

// ✅ RIGHT - App-wide user-customizable parameters (by admins)
Options::set('calendar.default_view', 'month');
$view = Options::get('calendar.default_view');
$view = options('calendar.default_view');

// ✅ RIGHT - Object-level settings (Property, unit, user...)
$property->set('timezone', 'America/Los_Angeles');
$timezone = $property->settings('timezone', 'UTC');
// Object-level settings have a model-defined fallback default rule
// E.g 
// - $user->settings() > Options::get() > Config::get()
// - $property->settings() > Options::get() > Config::get()
// - $unit->settings() > $property->settings() [ > Options::get() > Config::get() implied by property ]
```

### Database Changes

**Always use migrations**:
```php
// Never modify database manually
// Always create migrations for schema changes
php artisan make:migration add_capacity_to_units

// Migrations are automatically run by UpdateController
```

### Shared Components

**DataList - Single Source of Truth for Tables**:
```php
// ❌ WRONG - Hardcoded table
<table>
    <tr><th>Name</th><th>Price</th></tr>
    @foreach($items as $item)
        <tr><td>{{ $item->name }}</td><td>{{ $item->price }}</td></tr>
    @endforeach
</table>

// ✅ RIGHT - Use DataList
{!! Rate::list($rates, 'rates')
    ->groupBy('property_name')
    ->render() !!}

// ✅ RIGHT - Manual columns for arrays
{!! (new DataList($results))
    ->columns([
        'name' => ['label' => 'Name'],
        'price' => ['label' => 'Price', 'format' => 'currency']
    ])
    ->render() !!}
```

### Filament Admin Panel

The admin panel at `/admin` is built with Filament v5. Resources live in `app/Filament/Resources/`.

**Resource Structure**:
```
app/Filament/Resources/
├── Bookings/
│   ├── BookingResource.php          # Resource definition (navigation, pages)
│   ├── Forms/BookingForm.php        # Create/Edit form schema
│   ├── Infolists/BookingInfolist.php # View (read-only) schema
│   ├── Tables/BookingsTable.php     # Table columns, filters, actions
│   └── Pages/                       # List, Create, Edit, View pages
├── Properties/
├── Units/
├── Rates/
├── Users/
└── Support/
    └── DynamicTable.php             # Shared table column generator
```

**DynamicTable** (`app/Filament/Support/DynamicTable.php`):
Generates Filament table columns automatically from model configuration (`$list_columns`, `$casts`, `$appends`). Each Table class calls:
```php
DynamicTable::columns(Booking::class, 'booking', $overrides);
DynamicTable::recordActions(Booking::class, 'booking');
```

The second argument is the **lang prefix** used for `__("{prefix}.field.{col}")` labels.

**Translation keys in Filament**: All field labels, section headings, placeholders, and status options must use `__()` with the appropriate resource lang prefix. Never hardcode display text.

### CSS Architecture

**Use @apply with Tailwind utilities**:
```css
/* ✅ GOOD */
.nav-button {
    @apply inline-flex items-center justify-center w-10 h-10
           border border-light rounded-md hover:bg-gray-50;
}

/* ❌ BAD */
.nav-button {
    display: inline-flex;
    padding: 0.5rem;
    border: 1px solid #d1d5db;  /* Hardcoded, inconsistent */
}
```

**Container Queries for Responsive Components**:
```css
/* Tailwind breakpoints reference (for @container queries only) */
/* sm: 640px, md: 768px, lg: 1024px, xl: 1280px, 2xl: 1536px */

.rate-widget {
    container-type: inline-size;
}

@container (max-width: 640px) {
    .mobile-hidden {
        display: none;
    }
}
```

**File Organization**:
- `app.css` - Common styles needed by any layout
- `form.css` - Common styles needed for any form
- `calendar.css` - Calendar-specific styles
- `properties.css` - Property management styles
- `rates.css` - Rates-specific styles
- Each module has its own CSS file if it requires specific rules

### JavaScript

**No inline JavaScript in Blade templates**:
```blade
<!-- ❌ WRONG -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // inline code
    });
</script>

<!-- ✅ RIGHT -->
@vite(['resources/js/rate-calculator.js'])
```

**JavaScript file structure**:
```javascript
// resources/js/rate-calculator.js
document.addEventListener('DOMContentLoaded', () => {
    setupDateValidation();
    setupFormSubmission();
});

function setupDateValidation() {
    // Focused, testable functions
}
```

## Internationalization (i18n)

### Golden Rule

**All PHP code text is in English.** User-facing strings use `__()` translation keys. French (and other languages) are provided via translation files in `lang/fr/`, never hardcoded in PHP or Blade.

### Translation Key Conventions

```php
// Field labels follow the pattern: {resource}.field.{column}
__('booking.field.check_in')    // "Check-in"
__('rates.field.display_name')  // "Display Name"
__('unit.field.name')           // "Name"
__('user.field.email')          // "Email"

// Status values: {resource}.status.{key}
__('booking.status.confirmed')  // "Confirmed"
__('booking.status.cancelled')  // "Cancelled"

// Form sections: {resource}.section.{name}
__('booking.section.guests')    // "Guests"
__('booking.section.pricing')   // "Pricing"

// Common/shared labels
__('app.booking')               // "Booking"
__('app.property')              // "Property"
__('app.yes')                   // "Yes"
__('app.no')                    // "No"
```

### Translation Files

```
lang/
├── en/
│   ├── app.php        # Common/shared translations
│   ├── booking.php    # Booking resource (singular)
│   ├── property.php   # Property resource (singular)
│   ├── unit.php       # Unit resource (singular)
│   ├── rates.php      # Rates resource (exception: plural)
│   └── user.php       # User resource (singular)
└── fr/
    └── (same structure, French translations)
```

File names are **singular** (matching the model name), except `rates.php` which is plural for historical reasons.

### Rules

```php
// ✅ GOOD - User-facing text uses translation keys
<h1>{{ __('rates.calculator_title') }}</h1>
notice(__('rates.calculation_success'), 'success');

// ✅ GOOD - Logs and internal messages are English only, no translation
Log::info("Rate calculation completed for booking {$booking->id}");

// ❌ BAD - Hardcoded French in PHP
$label = 'Réservation';

// ❌ BAD - Hardcoded English user-facing text
$label = 'Booking'; // Should be __('app.booking')
```

## Testing

**Write tests for**:
- Business logic in services
- Model methods and relationships
- Calculator functions
- API endpoints

```php
// tests/Feature/RateCalculatorTest.php
public function test_calculates_correct_price_for_booking() {
    $rate = Rate::factory()->create(['base' => 100, 'formula' => 'base * nights']);
    $booking = Booking::factory()->create(['nights' => 5]);
    
    $calculator = new RateCalculator();
    $price = $calculator->calculate($booking, $rate);
    
    $this->assertEquals(500, $price);
}
```

## Security Best Practices

### Never Commit Sensitive Data
```bash
# tmp/ is gitignored - use it for:
- API keys
- User configurations
- Test data with real information
```

### Validate All Input
```php
$validated = $request->validate([
    'check_in' => 'required|date',
    'check_out' => 'required|date|after:check_in',
    'adults' => 'required|integer|min:1',
]);
```

### Use Authorization
```php
// Check permissions before actions
$this->authorize('update', $rate);

// Gate checks
if (Gate::denies('manage-properties')) {
    abort(403);
}
```

## Commit Message Format

```
(scope) short subject

- detail
- detail

Optional additional context.
```

- **scope**: area of the change — e.g. `(sync)`, `(beds24)`, `(filament)`, `(build)`, `(tests)`,
  `(doc)`, `(config)`
- **subject**: imperative, lowercase, no trailing period, 72 characters max
- **details**: bullet list with `-`, one item per logical change
- Omit details for trivial single-change commits
- Prefix with `(untested)` when the change has not been verified yet; reword after a successful
  test

**Examples**:
```
(rates) add calculator widget with property grouping

(untested) (calendar) correct date handling for DST transitions
```

**Rules**:
- Body explains what and why, not how
- English, whatever language the discussion happened in
- Reference issues with `Related to #456`; `Fix #123` on a commit that reaches the default branch
  actually closes the GitHub issue — and with it the two-way-ticket ticket linked to it
- **Never claim co-authorship** — no `Co-Authored-By` trailer, no tool credit, anywhere
- **Never push** — pushing is Oli's alone, and it is never offered either
- **Work on `master`.** This repo accumulated too many branches; unless told otherwise, commit
  straight to `master` rather than opening yet another one

## Version Releases

```
v1.2.3 Main change if applicable
- new ...
- new ...
- fix ...
- update ...
```

- **subject**: the first line begins exactly with `v` + the version number, so automated workflows
  and maintenance scripts can find it. A short description of the main change may follow if
  relevant
- **details**: the main changes since the previous version release commit
- Create a version release only when the version is fully tested and approved — bumping the version
  number in files does not mean the version must be released yet
- Be concise; the full explanation is in the git history
- Omit small patches and fixes, focus on essential features
- Bump the version in `composer.json`, in the `APP_VERSION` fallback of `config/app.php` and in the
  Version badge of README.md, and describe the release in CHANGELOG.md with the exact same wording
- The Stable badge is a separate decision, and yours: bump it when you declare a release stable,
  which an alpha, a beta or a release candidate is not
- After the commit, add a tag named `v1.2.3` with the exact same message as the commit

## Documentation

**Code Comments**:
```php
// Document WHY, not WHAT
// BAD: Loops through rates
foreach ($rates as $rate) {

// GOOD: Apply priority-based rate selection (unit > type > property)
foreach ($rates as $rate) {
```

**Doc Blocks** for public APIs:
```php
/**
 * Calculate booking price using applicable rate
 *
 * @param Booking $booking The booking to price
 * @param Rate|null $rate Optional rate override
 * @return float The calculated price
 * @throws RateNotFoundException If no applicable rate found
 */
public function calculatePrice(Booking $booking, ?Rate $rate = null): float
```

**Documentation Files**:
- `README.md` - Project overview (marketing tone, non-technical)
- `DEVELOPERS.md` - This file (development guide)
- `ROADMAP.md` - Feature timeline
- `AGENTS.md` - Rules for AI assistants
- `dev/*.md` - Technical deep-dives

## Module Development

See [dev/MODULES-ARCHITECTURE.md](dev/MODULES-ARCHITECTURE.md) for complete module development guide.

**Quick Start**:
```bash
# Create module structure
mkdir -p modules/my-module/{src,config,database/migrations,routes,resources}

# Create ServiceProvider
touch modules/my-module/src/MyModuleServiceProvider.php

# Register in AppServiceProvider
# Modules are auto-discovered from modules/ directory
```

## Performance Considerations

### Database
- Use eager loading to avoid N+1 queries
- Index foreign keys and frequently queried columns
- Use database transactions for multi-step operations

```php
// ❌ BAD - N+1 query problem
$bookings = Booking::all();
foreach ($bookings as $booking) {
    echo $booking->unit->name;  // Separate query each time
}

// ✅ GOOD - Eager loading
$bookings = Booking::with('unit')->get();
```

### Caching
```php
// Cache expensive operations
$properties = Cache::remember('properties.active', 3600, function () {
    return Property::where('is_active', true)->get();
});

// Invalidate when data changes
Cache::forget('properties.active');
```

### Asset Optimization
```bash
# Production builds are optimized
npm run build

# CSS is minified and purged of unused classes
# JavaScript is bundled and minimized
```

## Debugging

### Laravel Debug Tools
```bash
# View logs
tail -f storage/logs/laravel.log

# Tinker (REPL)
php artisan tinker
>>> App\Models\Booking::count()

# Clear caches
php artisan optimize:clear
```

### Desktop Commander
When working with AI assistants, use Desktop Commander for:
- File operations (read, write, search)
- Running commands (artisan, npm, git)
- Never ask users to paste outputs - fetch them directly

## Common Pitfalls

### ❌ Don't Hardcode Tables
Always use DataList for consistency

### ❌ Don't Use Direct CSS Properties
Use @apply with Tailwind utilities

### ❌ Don't Modify Unrelated Code
Stay focused on the current task

### ❌ Don't Create Backups Manually
Use Git for version control

### ❌ Don't Push Commits
Pushing is the project maintainer's responsibility

### ❌ Don't Mix Languages
Code/docs in English, UI in user's language

## Getting Help

### Documentation
- [ROADMAP.md](ROADMAP.md) - Planned features
- [dev/](dev/) - Technical documentation
- [Laravel Docs](https://laravel.com/docs) - Framework reference
- [Tailwind Docs](https://tailwindcss.com) - CSS framework

### Best Practices
- Ask clarifying questions before major changes
- Propose plans before large refactors
- Run verification commands before assuming
- Keep responses concise and actionable

---

**Remember**: The goal is to ship functional features incrementally while maintaining code quality and consistency. When in doubt, check existing patterns in the codebase or ask for clarification.
