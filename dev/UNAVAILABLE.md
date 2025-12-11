# Blocked Dates Management (Future Feature)

## Current Status

**Implemented:**
- iCal events with `SUMMARY:Unavailable` are filtered out and ignored during sync
- This prevents blocked dates from appearing as bookings

**Not Implemented:**
- Display of blocked dates in the calendar (grayed out)
- Management interface for blocked dates
- Support for multiple providers' blocking patterns

## Context

Calendar providers use different patterns to indicate unavailable dates:

### Beds24
- `SUMMARY:Unavailable`
- Description usually not useful
- These are periods where the unit cannot be booked (cleaning, maintenance, owner use, minimum stay gaps, etc.)

### Other Providers (to investigate)
- Airbnb: TBD
- Booking.com: TBD
- VRBO: TBD
- Custom iCal feeds: May vary

## Proposed Implementation

### Phase 1: Data Model

Add a `blocked_dates` table or extend `bookings` table with a `type` field:

```sql
-- Option 1: Separate table
CREATE TABLE blocked_dates (
    id INTEGER PRIMARY KEY,
    unit_id INTEGER NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255),
    source VARCHAR(50), -- 'manual', 'ical', 'system'
    source_name VARCHAR(255), -- iCal source name if applicable
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

-- Option 2: Extend bookings table
ALTER TABLE bookings ADD COLUMN type VARCHAR(20) DEFAULT 'booking';
-- Values: 'booking', 'blocked', 'maintenance', etc.
```

**Recommendation:** Extend `bookings` table with `type` field. Simpler, reuses existing sync logic, and allows unified calendar display.

### Phase 2: iCal Parsing

Update `IcalParser::extractEvents()` to recognize blocking patterns:

```php
protected function extractEvents(VObject\Component\VCalendar $vcalendar): array
{
    $events = [];
    
    foreach ($vcalendar->VEVENT as $vevent) {
        $summary = (string) ($vevent->SUMMARY ?? '');
        
        // Detect blocked dates by provider pattern
        $type = $this->detectEventType($summary);
        
        $event = [
            'uid' => (string) ($vevent->UID ?? null),
            'summary' => $summary,
            'description' => (string) ($vevent->DESCRIPTION ?? ''),
            'type' => $type, // 'booking' or 'blocked'
            'dtstart' => $this->parseDate($vevent->DTSTART),
            'dtend' => $this->parseDate($vevent->DTEND),
        ];
        
        if ($event['dtstart'] && $event['dtend']) {
            $events[] = $event;
        }
    }
    
    return $events;
}

protected function detectEventType(string $summary): string
{
    $blockPatterns = [
        '/unavailable/i',
        '/not available/i',
        '/blocked/i',
        '/owner block/i',
        // Add more patterns as we discover them
    ];
    
    foreach ($blockPatterns as $pattern) {
        if (preg_match($pattern, $summary)) {
            return 'blocked';
        }
    }
    
    return 'booking';
}
```

### Phase 3: Calendar Display

Update `dashboard.blade.php` to display blocked dates:

```php
// In the day cell loop
@if($blockDate)
    <div class="absolute inset-0 bg-gray-300 opacity-50 pointer-events-none">
        <div class="text-xs text-gray-600 p-1">🚫</div>
    </div>
@endif
```

Style options:
- Light gray overlay with opacity
- Diagonal stripes pattern
- "🚫" or "X" icon
- Tooltip on hover explaining reason

### Phase 4: Manual Management

Add UI for property managers to:
- Create manual blocks (maintenance, personal use, etc.)
- Edit/delete blocks
- Set recurring blocks (e.g., every Monday for cleaning)
- Import/export blocks

### Phase 5: Business Rules

Implement blocking rules:
- Minimum stay gaps (e.g., block 1 day between bookings if too short)
- Automatic buffer days after checkout
- Seasonal closures
- Day-of-week restrictions

## Provider Patterns Database

As we encounter different providers, document their patterns here:

| Provider | Pattern | Example | Notes |
|----------|---------|---------|-------|
| Beds24 | `Unavailable` | `SUMMARY:Unavailable` | No useful description |
| Airbnb | TBD | | |
| Booking.com | TBD | | |
| VRBO | TBD | | |

## Migration Strategy

When implementing:

1. Add `type` column to `bookings` table (default: 'booking')
2. Run migration to mark existing records as 'booking'
3. Update sync to populate `type` field
4. Update calendar view to render blocked dates
5. Add management UI

## Testing Checklist

- [ ] iCal with "Unavailable" entries syncs correctly
- [ ] Blocked dates don't show as bookings
- [ ] Blocked dates appear grayed out in calendar
- [ ] Manual blocks can be created
- [ ] Manual blocks prevent overlapping bookings
- [ ] Blocks sync correctly from multiple sources
- [ ] Expired blocks are cleaned up appropriately

## Related Files

- `app/Services/IcalParser.php` - Parsing logic
- `app/Models/Booking.php` - Data model
- `resources/views/dashboard.blade.php` - Display
- `app/Http/Controllers/DashboardController.php` - Data loading

## Questions to Answer

1. Should blocked dates prevent manual booking creation?
2. How to handle conflicts between different sources blocking the same dates?
3. Should past blocked dates be displayed or hidden?
4. What's the retention policy for old blocked dates?
5. Can users override iCal-sourced blocks?

## Priority

- **High:** Display blocked dates to avoid double-booking
- **Medium:** Manual block creation for property managers
- **Low:** Advanced rules and recurring blocks
