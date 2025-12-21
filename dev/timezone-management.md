# Timezone Management

## Storage vs Display

**Storage (DB/logs)**: Always `config('app.timezone')` = UTC (never change this)
**Display**: Uses `TimezoneTrait->timezone()` method

## TimezoneTrait

All models use `TimezoneTrait` which provides:
- `timezone()`: Returns the appropriate timezone for this model
- `displayDate($date, $format, $showTimezone)`: Formats dates with locale support

Each model defines its own hierarchy:
- **Unit**: unit.timezone > property.timezone > site > app
- **Booking**: booking.timezone > unit.timezone > property.timezone > site > app  
- **User**: user.timezone > site > app
- **Property**: property.timezone > site > app

## Display Formats

```php
$property->displayDate($date, 'long');        // Monday 21 December 2025 20:30
$property->displayDate($date, 'short');       // Mon 21 Dec 2025 20:30
$property->displayDate($date, 'date');        // 21 December 2025
$property->displayDate($date, 'date_short');  // 21 Dec 2025
$property->displayDate($date, 'time');        // 20:30
$property->displayDate($date, 'day');         // Monday 21 December
$property->displayDate($date, 'month');       // December 2025
$property->displayDate($date, 'Y-m-d H:i');   // Custom format
```

Formats use `translatedFormat()` for locale support (respects app.locale).

## Admin Configuration

Access: Admin menu > Settings

The display timezone selector:
- Lists all PHP timezones (`timezone_identifiers_list()`)
- Uses Select2 for searchable dropdown
- Saves to `Options::set('display.timezone')`
- Applies site-wide unless overridden per-property/unit

## Calendar Display

Calendar shows dates in the **first property's timezone** (not user timezone).
Rationale: User in Guadeloupe viewing Spain properties should see Spain dates.

## Testing

1. Admin > Settings > Set timezone to `America/Montreal` (GMT-4)
2. After midnight UTC, calendar should show correct local date
3. Timezone label appears next to month/year in calendar header

## Future: Per-Property Timezones

To enable:
1. Add `timezone` column to `properties` table
2. Override `timezone()` method in Property model
3. Same for Unit model if needed
