# Pricing System Implementation - Phase 1

## Overview
Phase 1 implementation provides basic pricing functionality with formula-based calculations and rate prioritization.

## Components Implemented

### 1. Database Schema

#### Rates Table
- `name`, `slug`: Rate identification
- `unit_id`, `unit_type`, `property_id`: Rate scope (mutually exclusive)
- `base_amount`: Base rate value
- `calculation_formula`: Mathematical formula (e.g., 'booking_nights * rate')
- `is_active`, `priority`: Rate selection criteria
- `settings`: JSON configuration

#### Pricing Calculations Table
- Stores calculation results for audit trail
- Contains calculation snapshot for debugging
- Links to booking records

#### Units Table Update
- Added `unit_type` field for categorization
- Simple string field (no separate model needed)

### 2. Models

#### Rate Model
- Scopes: `active()`, `forUnit()`, `forUnitType()`, `forProperty()`
- Relationships with Unit and Property
- Validation ensures exactly one scope is set

#### PricingCalculation Model
- Audit trail for pricing decisions
- Stores calculation variables and results

### 3. Pricing Calculator Service

#### Core Features
- Rate discovery with priority: unit > unit_type > property
- Formula evaluation with safety checks
- Variable substitution for calculations
- Automatic recalculation on booking changes

#### Available Variables
- `rate`: Base amount
- `booking_nights`: Number of nights
- `guests`, `adults`, `children`: Guest counts
- `check_in`, `check_out`: Dates
- `unit_id`, `property_id`: References

#### Formula Examples
- `'booking_nights * rate'` (standard per-night)
- `'booking_nights * guests * rate'` (per-night per-guest)
- `'booking_nights * adults * rate'` (per-night per-adult)
- `'rate'` (flat fee)

### 4. Integration

#### Booking Observer
- Automatic calculation on booking creation
- Recalculation on relevant field changes
- Error logging without blocking operations

#### API Endpoints
- Full CRUD for rates management
- Rate listing with filters
- Property/unit scoped queries

## Rate Priority System

### Selection Logic
1. **Unit Rate**: Highest priority, most specific
2. **Unit Type Rate**: Medium priority, shared by type
3. **Property Rate**: Lowest priority, default for property

### Resolution Order
```php
// Unit: "Studio Apartment", Type: "Studio", Property: "Beach Hotel"
// Rates configured:
// - Property rate: $100/night
// - Studio type rate: $80/night  
// - Specific unit rate: $120/night

// Result: $120/night (unit rate takes priority)
```

## Formula System

### Safety Features
- Variable replacement validation
- Character filtering (numbers, operators only)
- Parse error handling
- Result type validation

### Mathematical Operations
- Addition: `+`
- Subtraction: `-`
- Multiplication: `*`
- Division: `/`
- Parentheses: `()` for order of operations

### Advanced Examples
```php
// Minimum charge
'booking_nights * rate > 50 ? booking_nights * rate : 50'

// Weekend surcharge
'(booking_nights * rate) + (weekend_nights * rate * 0.2)'

// Guest-based pricing
'booking_nights * (rate + (guests > 2 ? (guests - 2) * 20 : 0))'
```

## API Usage

### Create Rates
```http
POST /api/rates
{
    "name": "Studio Rate",
    "unit_type": "studio",
    "base_amount": 80.00,
    "calculation_formula": "booking_nights * rate",
    "is_active": true
}
```

### List Rates
```http
GET /api/rates?property_id=1&unit_id=5
```

### Update Rates
```http
PUT /api/rates/123
{
    "base_amount": 85.00,
    "calculation_formula": "booking_nights * adults * rate"
}
```

## Channel Manager Compatibility

### OTA Integration Ready
- Flexible formula system matches various pricing models
- Unit type categorization aligns with common OTA structures
- Priority system handles multiple rate tiers
- JSON settings accommodate platform-specific fields

### Common OTA Patterns
- **Booking.com**: Per-night per-room
- **Airbnb**: Per-night with guest variations
- **Beds24**: Custom formulas supported
- **Lodgify**: Rate-based with supplements

## Testing Scenarios

### Basic Functionality
1. Create property rate → Verify unit bookings use property rate
2. Add unit type rate → Verify units of that type use type rate
3. Add unit-specific rate → Verify that unit uses specific rate

### Formula Testing
1. Simple formula: `booking_nights * rate`
2. Per-guest: `booking_nights * guests * rate`
3. Per-adult: `booking_nights * adults * rate`
4. Complex: `rate + (guests > 2 ? (guests - 2) * 20 : 0)`

### Priority Testing
1. All three rates exist → Unit rate selected
2. Unit + property rates → Unit rate selected
3. Type + property rates → Type rate selected
4. Only property rate → Property rate selected

## Migration Strategy

### Existing Data
- `unit_type` field is nullable for backward compatibility
- Current `price` values preserved during transition
- Existing bookings get calculations on next update

### Deployment Steps
1. Migration runs automatically (new tables, unit_type column)
2. Observer registration in AppServiceProvider
3. API routes available immediately
4. UI components can be added progressively

## Performance Considerations

### Optimization Points
- Rate queries use indexed columns
- Formula evaluation is lightweight
- Calculation snapshots stored for audit, not recomputation
- Observer only triggers on relevant field changes

### Caching Opportunities
- Rate lookup results per unit/property
- Formula compilation (future enhancement)
- Pre-calculated seasonal rates (future)

## Next Phase Preparation

### Extension Points
- JSON `settings` field for advanced rules
- Formula engine ready for conditionals
- Priority system supports more tiers
- Audit trail enables rate change tracking

### Phase 2 Foundations
- Rate priority infrastructure ready
- Formula evaluation system extensible
- Rate scope validation implemented
- Observer pattern in place for complex rules

## Conclusion

Phase 1 provides a solid, production-ready pricing foundation that:
- Handles immediate rate calculation needs
- Supports OTA integration patterns
- Maintains performance and auditability
- Provides clear path for Phase 2 enhancements

The system is minimal yet complete, allowing rapid deployment while ensuring future compatibility.