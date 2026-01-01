# Data Filtering by User Authorization

## Overview

All queries in Bokit Light are automatically filtered based on the current user's authorization level. This prevents users from accessing data they shouldn't see.

## How It Works

### The `forUser()` Scope

Every model that uses `AdminResourceTrait` gets a `forUser()` scope that automatically filters records:

```php
// Automatically filtered for current user
$properties = Property::forUser()->get();

// Admin/manager sees everything
// property_manager sees only their properties
```

### Authorization Levels

1. **Admin/Manager** → See everything (no filter applied)
2. **property_manager** → See only records they own or have access to
3. **Other roles** → No access by default

### Property Ownership via Many-to-Many

Properties are linked to users via the `property_user` pivot table:

```
properties         property_user         users
----------         -------------         -----
id                 property_id           id
name               user_id               name
...                role                  roles
                   timestamps            ...
```

The `whereHas('users')` query checks this relationship:

```php
// Under the hood
Property::whereHas('users', function ($q) {
    $q->where('users.id', auth()->id());
})

// SQL equivalent
SELECT * FROM properties 
WHERE EXISTS (
    SELECT * FROM property_user 
    WHERE property_user.property_id = properties.id
    AND property_user.user_id = ?
)
```

### Related Models Filtering

Models related to properties (Unit, Booking, Rate) filter via `property.users`:

```php
// Units filter via their property's users
Unit::forUser()->get()

// Equivalent to
Unit::whereHas('property.users', function ($q) {
    $q->where('users.id', auth()->id());
})->get()
```

## Usage Rules

### ✅ DO: Always use `forUser()`

```php
// In controllers
$properties = Property::forUser()->get();
$units = Unit::forUser()->get();
$bookings = Booking::forUser()->get();

// Can be chained with other methods
$active = Property::forUser()
    ->where('is_active', true)
    ->orderBy('name')
    ->get();
```

### ❌ DON'T: Direct queries without filtering

```php
// WRONG - bypasses authorization
Property::all();
Property::where('name', 'like', '%test%')->get();
Unit::find($id); // findOrFail is OK if followed by checkObjectAccess()
```

### Automatic Filtering in Lists

The `ListTrait::list()` method automatically uses `forUser()`:

```php
// In admin views
{{ Property::list() }} // Automatically filtered

// Custom collection
{{ Property::list($customCollection) }}
```

## Implementation Details

### Default Behavior (AdminResourceTrait)

```php
public function scopeForUser(Builder $query, $user = null): Builder
{
    $user = $user ?? auth()->user();
    
    // Admin/manager: no filtering
    if (!$user || $user->isAdmin() || $user->hasRole('manager')) {
        return $query;
    }
    
    // property_manager: filter by ownership
    if ($user->hasRole('property_manager')) {
        return $this->scopeForPropertyManager($query, $user);
    }
    
    // Other roles: no access
    return $query->whereRaw('1 = 0');
}

protected function scopeForPropertyManager(Builder $query, $user): Builder
{
    // For Property model: direct users relationship
    if ($this instanceof \App\Models\Property) {
        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
    
    // For related models: filter via property.users
    if (method_exists($this, 'property')) {
        return $query->whereHas('property.users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
    
    // No property relationship: no access
    return $query->whereRaw('1 = 0');
}
```

### Custom Filtering (override in model if needed)

```php
class CustomModel extends Model
{
    use AdminResourceTrait;
    
    // Override if you need custom filtering logic
    protected function scopeForPropertyManager(Builder $query, $user): Builder
    {
        // Custom logic here
        return $query->where('owner_id', $user->id);
    }
}
```

## Examples

### Controller Usage

```php
class PropertyController extends Controller
{
    public function index()
    {
        // Before (manual filtering)
        $query = Property::with('units');
        if (!user_can('super_admin')) {
            $query->whereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            });
        }
        $properties = $query->get();
        
        // After (automatic filtering)
        $properties = Property::with('units')->forUser()->get();
    }
}
```

### View Usage

```php
// In Blade templates - automatic filtering via list()
{{ Property::list() }}

// Or manual query
@php
    $myProperties = Property::forUser()->get();
@endphp
```

## Security Checklist

- ✅ All queries use `forUser()`
- ✅ `ListTrait::list()` defaults to `forUser()->get()`
- ✅ Controllers check ownership with `checkObjectAccess()`
- ✅ Admin interface respects capability + ownership
- ✅ No direct `::all()`, `::where()`, or `::get()` without `forUser()`

## Common Patterns

### Loading with relationships

```php
// Load properties with their units (both filtered)
$properties = Property::with('units')->forUser()->get();
```

### Filtering with additional conditions

```php
// Get active properties for current user
$active = Property::forUser()
    ->where('is_active', true)
    ->get();
```

### Counting records

```php
// Count user's properties
$count = Property::forUser()->count();
```

### Pagination

```php
// Paginate user's properties
$properties = Property::forUser()->paginate(20);
```

## Testing

When testing, you can pass a specific user:

```php
// Test as specific user
$properties = Property::forUser($testUser)->get();

// Test as admin (should see everything)
$allProperties = Property::forUser($adminUser)->get();
```
