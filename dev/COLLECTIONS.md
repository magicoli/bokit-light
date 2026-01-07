# Collections Architecture

## Overview

This document outlines the collections architecture for Bokit, focusing on data formatting and export capabilities while maintaining real-time data integrity.

## Core Principles

### 1. Single Source of Truth
- Collections are the central data structure used throughout the application
- Same collection powers calendar views, admin lists, and exports
- No duplicate queries or data fetching logic

### 2. Real-time Data
- Collections always reflect current database state
- No caching of collections themselves
- Only export outputs may be cached (ICS, XML, etc.)

### 3. Format Agnostic
- Collections can be converted to any format without data duplication
- Formatting logic is separate from data fetching
- Extensible for future formats (JSON, XML, ICS, PDF, etc.)

## Architecture Components

### CollectionTrait
Provides universal collection methods for all models:

```php
trait CollectionTrait 
{
    public function toCollection(): Collection
    public function toArray(array $context = []): array
    public function toJson(array $context = []): string
    public function toXml(array $context = []): string
    
    // Conditional methods (if model has required attributes)
    public function toIcs(array $context = []): ?string
    public function toPdf(array $context = []): ?string
}
```

### ModelConfigTrait Integration
Acts as the central hub for all model traits:

```php
trait ModelConfigTrait
{
    use CollectionTrait, ListTrait, FormTrait, TimezoneTrait;
    
    // All models automatically inherit all collection capabilities
}
```

### Formatter Registry
Handles optional module formatters:

```php
class FormatterRegistry 
{
    public static function register(string $format, callable $formatter): void
    public static function get(string $format): ?callable
}
```

## Data Flow

### 1. Collection Creation
```php
// Same collection, multiple uses
$unit->bookings();                    // Default collection
$unit->bookings(['status' => 'confirmed']); // Filtered collection
```

### 2. Format Conversion
```php
// Same data, different formats
$collection = $unit->bookings();
$array = $collection->toArray();
$json = $collection->toJson();
$ics = $collection->toIcs()->arrivals();
```

### 3. Configuration-driven
```php
// Model config determines formatting rules
'formatters' => [
    'ics' => [
        'start_field' => 'check_in',
        'end_field' => 'check_out',
        'summary_field' => 'guest_name',
        'description_template' => 'booking.ics_)description',
    ]
]
```

## ICS Export Implementation

### URL Pattern
Secure, non-guessable URLs with permanent tokens:

```
/{property_slug}/{unit_slug}/arrivals-{token}.ics
/{property_slug}/{unit_slug}/bookings-{token}.ics
/{property_slug}/{unit_slug}/departures-{token}.ics
/{property_slug}/{unit_slug}/occupied-{token}.ics
/{property_slug}/bookings-{token}.ics
```

### Token Management
- **Permanent tokens**: Never expire unless manually revoked
- **Type-specific scope**: One token per property, unit and export type for security
- **Revocable**: Can be manually revoked to break subscriptions

### ICS Variants
1. **occupied**: (default) Anonymous occupied dates (no guest details)
2. **bookings**: Full booking details with guest information
3. **arrivals**: Arrival dates only (check-in events)
4. **departures**: Departure dates only (check-out events)

### ICS Method Syntax
```php
// Simple parameter-based syntax
$bookings->toIcs();               // Default = Anonymous occupied
$bookings->toIcs("occupied");     // Anonymous occupied
$bookings->toIcs("bookings");     // Full bookings
$bookings->toIcs("arrivals");     // Arrivals only
$bookings->toIcs("departures");   // Departures only
```

## Property vs Unit Exports

Both levels are supported:

### Property Level
- All units combined under one property
- Useful for property-wide calendar views
- URL: `/{property_slug}/bookings-{token}.ics`

### Unit Level
- Individual unit calendars
- More granular control
- URL: `/{property_slug}/{unit_slug}/bookings-{token}.ics`

## Caching Strategy

### Collections: No Caching
- Always reflect real-time database state
- Central to application functionality
- Performance acceptable (max 365 items per unit)

### Export Outputs: Optional Caching
- ICS files may be cached for performance
- Cache invalidation on data changes
- Configurable per export type

## Module Integration

### Optional Formatters
Modules can register custom formatters.

Modules will be registered globally with a process not yet implemented.

Once registered, they should be able to add their own formatters.

TBD later.

### Backward Compatibility
- Main application works without any modules
- Modules enhance but don't break core functionality
- Graceful fallback when modules are missing

## Implementation Phases

### Phase 1: Infrastructure
- Create `CollectionTrait` with basic methods
- Integrate into `ModelConfigTrait`
- Extend `getConfig()` for formatter configuration

### Phase 2: ICS Implementation
- Implement `Booking::toIcs($type="occupied)` with 4 variants
- Create ICS templates and token management

### Phase 3: Routes and Security
- Implement secure token generation
- Create export routes with validation
- Add controller logic for ICS generation

### Phase 4: Testing and Validation
- Unit tests for all components
- Integration tests with real data
- ICS format validation

## Performance Considerations

### Collection Size
- Maximum: 365 bookings per unit per year
- Typical: 10-50 bookings per unit
- Memory usage: Acceptable (< 10MB per collection)

### Export Generation
- On-demand generation (no pre-generation)
- Optional output caching
- Streaming not needed for small collections

### Database Queries
- Single query per collection
- Leverage existing `ListTrait` logic
- No duplicate query logic

## Security Considerations

### Token Security
- Cryptographically secure random tokens
- Type-specific access control
- Manual revocation capability

### Data Exposure
- Anonymous exports for sensitive data
- Type-specific scoping prevents privilege escalation
- No parameter passing (all in URL structure)

## Future Extensibility

### Additional Formats
- XML for API integrations
- PDF for reports and invoices
- Custom formats via modules

### Advanced Features
- Date range filtering
- Multi-unit exports
- Subscription management

## Notes

- This architecture prioritizes simplicity and real-time accuracy
- Performance is acceptable for the expected data volumes
- Security is built-in with token-based access control
- Extensibility is provided through the formatter registry
