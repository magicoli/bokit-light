# Three-Way Merge Sync System

## Overview

This system prevents sync operations from overwriting local manual changes by implementing a three-way merge algorithm. It tracks three versions of data:

1. **Local** - Current values in the model (managed/corrected)
2. **Remote** - New values from sync source (raw CM/OTA data)
3. **Baseline** - Last synced values (stored in `sync_data`)

**Merge Logic:**  
```
if (local == baseline) → Accept remote (no local changes)
else → Keep local (manual edit detected, preserve it)
```

**Important:** Local differences are **intentional management edits** (corrected names, adjusted times, added details), not "conflicts" to resolve. Both local and remote values have their purpose and should be visible to users.

## Database Structure

### `bookings.sync_data` JSON Column

Source identifier format: `{platform}_{method}` (e.g., `airbnb_ical`, `beds24_api`)

```json
{
  "airbnb_ical": {
    "raw": {...},           // Raw data from source
    "processed": {...},     // Processed/normalized data
    "synced_at": "2026-01-08T12:00:00Z"
  },
  "beds24_api": {
    "raw": {...},
    "processed": {...},
    "synced_at": "2026-01-08T13:00:00Z"
  }
}
```

### `sync_logs` Table

Tracks all changes (sync + manual edits):

| Column | Type | Description |
|--------|------|-------------|
| model_type | string | `App\Models\Booking` |
| model_id | bigint | Record ID |
| source | string | `airbnb_ical`, `beds24_api`, `user:email@example.com` |
| field | string | Field name changed |
| old_value | text | Previous value |
| new_value | text | New value |
| created_at | timestamp | When changed |

**Source format:**
- Sync: `{platform}_{method}` (e.g., `airbnb_ical`, `beds24_api`)
- User: `user:{email}` (e.g., `user:admin@example.com`)

## Usage

### Applying Sync Data

```php
use App\Support\SyncResolver;

$booking = Booking::find(123);

$newData = [
    'guest_name' => 'John Doe Enterprise',
    'guests' => 4,
    'price' => 150.00,
];

// Use source identifier: platform_method
$result = $booking->applySyncData($newData, 'airbnb_ical');

// Or use SyncResolver directly:
$result = SyncResolver::applySyncData(
    $booking,
    $newData,
    'beds24_api',
    $fieldMapping = [] // Optional: map sync fields to model attributes
);
```

**Returns:**
```php
[
    'updated' => ['guests', 'price'],      // Fields that were updated
    'diffs' => [                           // Fields with local edits (preserved)
        [
            'field' => 'guest_name',
            'local' => 'John Doe',         // Managed value (kept)
            'remote' => 'John Doe Enterprise', // Sync value (ignored)
            'baseline' => 'J. Doe'
        ]
    ]
]
```

### Getting Sync Differences

```php
// Get all diffs for first source
$diffs = $booking->getSyncDiffs();

// Get diffs for specific source
$diffs = $booking->getSyncDiffs('airbnb_ical');

// Returns:
[
    'guest_name' => [
        'local' => 'John Doe',              // Our managed value
        'remote' => 'John Doe Enterprise'   // Their sync value
    ],
    'guests' => [
        'local' => 3,
        'remote' => 4
    ]
]
```

### Viewing Sync Logs

```php
// Get all sync logs for a booking
$logs = $booking->syncLogs()->latest()->get();

// Get only sync-initiated changes
$syncLogs = $booking->syncLogs()
    ->where('source', 'like', '%_ical')
    ->orWhere('source', 'like', '%_api')
    ->get();

// Get only user edits
$userLogs = $booking->syncLogs()
    ->where('source', 'like', 'user:%')
    ->get();
```

### Manual Change Logging

Changes are automatically logged when using `SyncResolver`, but you can also log manual changes:

```php
use App\Models\SyncLog;

SyncLog::logChange(
    $booking,
    'guest_name',
    'Old Name',
    'New Name',
    'user:' . auth()->user()->email
);
```

## Integration with Existing Sync

### Example: iCal Sync

```php
// In IcalService or similar
public function syncBooking(Unit $unit, array $icalEvent)
{
    $booking = Booking::firstOrNew(['uid' => $icalEvent['uid']]);
    
    // Source format: platform_method
    $source = 'airbnb_ical'; // or 'beds24_ical', 'vrbo_ical', etc.
    
    $newData = [
        'guest_name' => $icalEvent['summary'],
        'check_in' => $icalEvent['start'],
        'check_out' => $icalEvent['end'],
        'guests' => $this->extractGuests($icalEvent),
    ];
    
    if ($booking->exists) {
        // Use three-way merge for updates
        $result = $booking->applySyncData($newData, $source);
        
        if (!empty($result['diffs'])) {
            Log::info("Sync diffs detected (local edits preserved)", [
                'booking_id' => $booking->id,
                'diffs' => $result['diffs']
            ]);
        }
    } else {
        // New booking - direct assignment
        $booking->fill($newData);
        $booking->save();
        
        // Set initial baseline
        $booking->sync_data = [
            $source => [
                'raw' => $icalEvent,
                'processed' => $newData,
                'synced_at' => now()->toIso8601String(),
            ]
        ];
        $booking->save();
    }
    
    return $booking;
}
```

## UI Integration

### Displaying Diffs in Views

In Edit/Show views, display both local (managed) and remote (sync) values:

```blade
@php
$diffs = $booking->getSyncDiffs();
@endphp

<div class="field">
    <label>Guest Name</label>
    <input type="text" name="guest_name" value="{{ $booking->guest_name }}">
    
    @if(isset($diffs['guest_name']))
        <div class="sync-diff">
            <span class="remote">{{ $diffs['guest_name']['remote'] }}</span>
        </div>
    @endif
</div>
```

**Display format:**
```
Name: [ John Doe ] J. Doe Enterprise SARL
       ↑ local      ↑ remote (from sync)
```

The local value (in brackets) is what we manage, the remote value shows what the CM/OTA has.

### CSS Styling

```css
.sync-diff {
    display: inline-flex;
    gap: 0.5rem;
    font-size: 0.875rem;
    margin-left: 0.5rem;
}

.sync-diff .remote {
    color: #6b7280; /* Gray for remote sync value */
    font-style: italic;
}
```

## Source Identifier Convention

Format: `{platform}_{method}`

**Examples:**
- `airbnb_ical` - Airbnb via iCal feed
- `airbnb_api` - Airbnb via direct API
- `beds24_ical` - Beds24 via iCal feed
- `beds24_api` - Beds24 via API
- `vrbo_ical` - VRBO via iCal feed
- `booking_ical` - Booking.com via iCal feed
- `user:admin@example.com` - Manual edit by user

This format ensures unique identifiers without needing separate source/name fields.

## Benefits

1. **No Data Loss** - Local edits never overwritten by sync
2. **Automatic Detection** - No manual tracking needed
3. **Full Audit Trail** - Every change logged with source
4. **Multi-Source Support** - Track different sync sources separately
5. **Simple Integration** - One method call: `applySyncData()`
6. **Intentional Clarity** - Diffs are managed edits, not errors

## Future Enhancements

- UI for displaying diffs inline with field values
- Sync history viewer showing all changes over time
- "Accept remote" button for individual fields if needed
- Bulk diff review page
- Visual diff timeline
