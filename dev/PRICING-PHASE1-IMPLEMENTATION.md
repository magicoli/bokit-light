# Rate System - Current Implementation

## Overview

This document describes the **current implementation** of Bokit's rate calculation system. This is a living document reflecting the actual codebase, not a proposal.

**Last Updated:** 2025-12-26  
**Status:** Partially complete - core functionality working, variations and combinations pending

## Current Architecture

### Database Schema

#### Rates Table

```sql
CREATE TABLE rates (
    id INTEGER PRIMARY KEY,
    property_id INTEGER,           -- Scope: property-level rate
    unit_type VARCHAR(50),         -- Scope: unit type rate  
    unit_id INTEGER,               -- Scope: specific unit rate
    
    parent_rate_id INTEGER,        -- Hierarchical rates (variations)
    
    name VARCHAR(255),             -- Display name (nullable)
    slug VARCHAR(255),             -- URL-friendly identifier (nullable)
    
    base DECIMAL(10,2) NOT NULL,   -- Base rate amount
    formula TEXT,                  -- Calculation formula
    
    priority INTEGER,              -- Selection priority (nullable)
    is_active BOOLEAN DEFAULT 1,   -- Active status
    
    settings JSON,                 -- Additional configuration
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    -- Constraints
    CHECK ((property_id IS NOT NULL) + (unit_type IS NOT NULL) + (unit_id IS NOT NULL) = 1)
    FOREIGN KEY (parent_rate_id) REFERENCES rates(id) ON DELETE CASCADE
);
```

**Key Fields:**
- `base` - Base rate value (renamed from `base_amount`)
- `parent_rate_id` - Links to parent rate for variations
- One of `property_id`, `unit_type`, or `unit_id` must be set (mutually exclusive)

#### Units Table (Capacity Fields)

```sql
ALTER TABLE units ADD COLUMN bedrooms INTEGER;
ALTER TABLE units ADD COLUMN max_guests INTEGER;
```

Enables capacity-based filtering in rate calculator.

### Models

#### Rate Model (`app/Models/Rate.php`)

**Scopes:**
```php
Rate::active()           // is_active = 1
Rate::forUnit($unitId)   // unit_id = $unitId
Rate::forUnitType($type) // unit_type = $type
Rate::forProperty($id)   // property_id = $id
```

**Relationships:**
```php
$rate->property()     // BelongsTo Property
$rate->unit()         // BelongsTo Unit
$rate->parentRate()   // BelongsTo Rate (parent)
$rate->children()     // HasMany Rate (variations)
```

**Model Events:**
```php
// When parent rate base changes, sync to children
static::updating(function ($rate) {
    if ($rate->isDirty('base') && !$rate->parent_rate_id) {
        $rate->children()->update(['base' => $rate->base]);
    }
});
```

**Validation:**
- Exactly one scope field (property_id, unit_type, unit_id) must be set
- Children cannot have other children (max 2 levels)
- Formula syntax validation

### Rate Calculator Service

**Not yet implemented as separate service class**

Currently logic is in `RatesController::calculate()`.

**Future:** Extract to `app/Services/RateCalculator.php` for reusability.

### Calculation Logic

#### Priority System

Rates are selected using scope-based priority:

1. **Unit-specific** (highest priority)
2. **Unit type**
3. **Property-wide** (lowest priority)

```php
// Pseudocode
$rate = Rate::forUnit($unitId)->first() 
     ?? Rate::forUnitType($unit->unit_type)->first()
     ?? Rate::forProperty($unit->property_id)->first();
```

#### Formula Evaluation

**Available Variables:**
- `base` - Base rate from database
- `nights` - Number of nights (check_out - check_in)
- `guests` - Total guest count
- `adults` - Adult count
- `children` - Children count

**Example Formulas:**
```php
'base * nights'                    // Simple nightly rate
'base * nights * 0.9'              // 10% discount
'base * nights + (guests * 10)'    // Base + per-guest fee
```

**Evaluation:**
```php
// In controller (simplified)
$variables = compact('base', 'nights', 'guests', 'adults', 'children');
$formula = str_replace(array_keys($variables), array_values($variables), $rate->formula);
$total = eval("return $formula;");
```

**Security:** Formula evaluation uses whitelisted variables only, no user input.

### Parent Rate Architecture (Variations)

**Concept:** Base rates can have "variations" that inherit and modify the base.

**Example:**
```
Base Rate: "Summer Nights" (base: 100, formula: 'base * nights')
  ├─ Variation: "Long Stay Discount" (base: 100, formula: 'base * nights * 0.85')
  └─ Variation: "Early Bird" (base: 100, formula: 'base * nights * 0.90')
```

**Key Points:**
- Parent `base` auto-syncs to children (via model events)
- Children can override `formula` for different calculations
- Maximum 2 levels (parent → child, no grandchildren)
- Used for seasonal rates, promotions, discounts

**Current Status:** ✅ Architecture implemented, not yet exposed in UI

## User Interface

### Rate Calculator Widget

**Location:** `resources/views/components/rate-calculator.blade.php`

**Features:**
- Date range picker (check-in, check-out)
- Guest count selectors (adults, children)
- Capacity filtering (bedrooms, max guests)
- Results grouped by property
- Responsive column hiding (container queries)

**Result Columns:**
- Unit name
- Rate name (hidden on mobile)
- Price per night
- Total price

**DataList Integration:**
```php
{!! (new DataList($results))
    ->groupBy('property_name')
    ->columns([
        'unit_name' => ['label' => __('rates.unit')],
        'rate_name' => ['label' => __('rates.rate'), 'class' => 'mobile-hidden'],
        'price_per_night' => ['label' => __('rates.price_per_night'), 'format' => 'currency'],
        'total' => ['label' => __('rates.total'), 'format' => 'currency'],
    ])
    ->render() !!}
```

**Styling:** Uses container queries for responsive design (see `resources/css/rates.css`)

### Rate Management (Admin)

**Location:** `/rates`

**Features:**
- List all rates (DataList)
- Create new rates
- Edit existing rates
- Scope selection (property/unit type/unit)
- Formula builder
- Parent rate selection (for variations)

**Form Fields:**
- Scope: Radio buttons (property, unit type, unit)
- Name: Optional display name
- Base: Required numeric value
- Formula: Text input with variable reference
- Parent Rate: Dropdown (optional)
- Priority: Numeric (nullable)
- Is Active: Checkbox

## Testing

**Manual Testing:**
1. Navigate to `/rates/calculator`
2. Select dates and guests
3. Verify results display correctly
4. Test capacity filtering (units with insufficient bedrooms hidden)
5. Test responsive behavior (hide Rate column on mobile)

**Future:** Add automated tests for calculation logic.

## Known Issues & Limitations

### Current Limitations

1. **No variations UI** - Parent rate architecture exists but no UI to create variations
2. **No unit combinations** - Cannot calculate for multi-unit bookings
3. **No access control** - All users see all properties in calculator
4. **Simple formulas only** - No advanced conditions (date ranges, day of week)

### Bugs to Fix

1. **Parent rate base sync** - Model event might not trigger in all cases
2. **No units message** - Calculator should display message when no units match criteria
3. **Currency formatting** - Hardcoded to 2 decimals, should respect locale

See [ISSUES.md](ISSUES.md) and [ROADMAP.md](../ROADMAP.md) for detailed tracking.

## Development Notes

### Adding New Variables

To add a new formula variable:

1. Add to calculation in `RatesController::calculate()`:
```php
$variables = [
    'base' => $rate->base,
    'nights' => $nights,
    'new_var' => $someValue, // Add here
];
```

2. Document in formula help text
3. Update translations

### Creating Variations

**Current workflow (programmatic):**
```php
// Create base rate
$base = Rate::create([
    'property_id' => 1,
    'base' => 100,
    'formula' => 'base * nights',
]);

// Create variation
$variation = Rate::create([
    'property_id' => 1,
    'parent_rate_id' => $base->id,
    'base' => 100, // Inherited
    'formula' => 'base * nights * 0.85', // Modified
]);
```

**Future:** UI for variation creation in rate form.

## Files Modified

### Backend
- `app/Models/Rate.php` - Model with parent rate relationship
- `app/Http/Controllers/RatesController.php` - Calculation logic
- `database/migrations/*_rename_base_rate_to_base.php` - Field rename
- `database/migrations/*_add_bedrooms_and_max_guests.php` - Capacity fields

### Frontend
- `resources/views/components/rate-calculator.blade.php` - Calculator widget
- `resources/views/rates/index.blade.php` - Rate management
- `resources/views/rates/form.blade.php` - Create/edit form
- `resources/css/rates.css` - Rate-specific styles

### Translations
- `lang/en/rates.php` - English strings
- `lang/fr/rates.php` - French strings

## Next Steps

See [ROADMAP.md](../ROADMAP.md) Phase 1, Section 1 for planned enhancements:

1. Fix parent_rate calculation bug
2. Display message when no units available  
3. Implement unit combinations (multi-unit bookings)
4. Implement rate variations system (UI)
5. Property access control in calculator

---

**Note:** This document reflects the actual implemented system. For proposed features, see ROADMAP.md.
