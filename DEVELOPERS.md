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
│   ├── Http/Controllers/      # Request handlers
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic
│   └── Support/               # Helpers (DataList, etc.)
├── database/
│   └── migrations/            # Database schema versions
├── modules/                   # Optional premium/integration modules
├── resources/
│   ├── css/                   # Modular stylesheets
│   ├── js/                    # JavaScript components
│   ├── lang/                  # Translations (en, fr)
│   └── views/                 # Blade templates
├── routes/
│   ├── admin.php             # Admin zone routes
│   ├── web.php               # Application routes
│   └── console.php           # Scheduler configuration
├── dev/                      # Development documentation
└── tmp/                      # Temporary files (gitignored)
```

## Development Workflow

### Environment Setup

1. **Local Development Server**:
   ```bash
   symfony serve -d  # Preferred for Mac (HTTPS support)
   # or
   php artisan serve
   ```
   Access at: https://localhost:8000

2. **Asset Compilation**:
   ```bash
   npm run dev    # Watch mode
   npm run build  # Production build
   ```

3. **Database Migrations**:
   ```bash
   php artisan migrate
   php artisan migrate:fresh  # Reset database
   ```

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
   php artisan test               # Run tests
   npm run build                  # Ensure assets compile
   php artisan migrate:fresh      # Test migrations
   ```

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

**All displayed text must be localizable**:
```php
// ✅ GOOD - User-facing text
<h1>{{ __('rates.calculator_title') }}</h1>
notice(__('rates.calculation_success'), 'success');

// ✅ GOOD - Logs and internal messages are English only
Log::info("Rate calculation completed for booking {$booking->id}");
```

**Translation files**:
```
lang/
├── en/
│   ├── app.php      # Common translations
│   ├── rates.php    # Rates module
│   └── forms.php    # Form labels
└── fr/
    ├── app.php
    ├── rates.php
    └── forms.php
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

Follow Conventional Commits specification:

```
type(scope): short description

- Bullet point summary of changes
- Reference issue numbers if applicable
- Keep subject line under 72 characters
```

**Types**:
- `feat` - New feature
- `fix` - Bug fix
- `chore` - Maintenance (deps, config)
- `docs` - Documentation only
- `test` - Adding tests
- `perf` - Performance improvement
- `refactor` - Code restructuring
- `style` - Formatting, whitespace
- `ci` - CI/CD changes
- `build` - Build system changes
- `revert` - Revert previous commit

**Examples**:
```bash
feat(rates): add calculator widget with property grouping
fix(calendar): correct date handling for DST transitions
chore(deps): update Laravel to 11.x
docs(readme): update installation instructions
```

**Rules**:
- First line: 72 characters max
- Body: Explain what and why, not how
- Reference issues: "Fixes #123" or "Related to #456"
- **Never push commits** - pushing is the user's responsibility

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
