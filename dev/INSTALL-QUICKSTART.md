# Bokit - Quick Start

## IMPORTANT: Fresh Setup Required

The date handling has been corrected. iCal dates are already in the correct format (real check-in/check-out dates).

**If you have existing data, do a fresh setup:**

```bash
# Complete reset
php artisan migrate:fresh

# Import config
php artisan bokit:import-config

# Sync calendars
php artisan bokit:sync
```

## What's working ✅

- 5 properties configured (Moon, Sun, Violeta, Zandoli, Zetoil)
- Correct date handling (iCal dates = real check-in/check-out)
- Horizontal calendar view - one row per property
- Visual blocks from check-in noon to check-out noon
- Full-width calendar display
- Click anywhere on booking to see details
- Mobile-responsive

## Start the server

```bash
./dev/start-server.sh
```

Then open: **http://localhost:8000**

## Navigation

- **‹ ›**: Navigate month by month
- **« »**: Jump one year back/forward  
- **Today**: Return to current date
- **Week/Month toggle**: (mobile only) Switch between views

## Delete test property

```bash
php artisan bokit:cleanup-property villa-test
```

## Sync calendars

```bash
# Manual sync
php artisan bokit:sync

# Setup cron for automatic hourly sync
* * * * * cd /path/to/bokit-light && php artisan schedule:run >> /dev/null 2>&1
```

Schedule is already configured in `routes/console.php`.

## About dates

- **iCal format**: DTSTART and DTEND are already real check-in/check-out dates
- **Internal storage**: Same as iCal (no conversion needed)
- **Display**: Blocks show from check-in noon to check-out noon
- **Example**: iCal shows Dec 21-26 → Guest arrives Dec 21 noon, departs Dec 26 noon (5 nights)

## Verify a booking

```bash
php artisan tinker
>>> $b = App\Models\Booking::where('guest_name', 'LIKE', '%Fabienne%')->first();
>>> echo "Check-in: {$b->check_in}, Check-out: {$b->check_out}, Nights: {$b->nights()}";
```

Should show: `Check-in: 2025-12-21, Check-out: 2025-12-26, Nights: 5`

## Next steps

Phase 1 complete! For Phase 2:
- Notes on bookings
- Manual bookings  
- Export consolidated iCal
- Grouped bookings
- Additional fields (guests, price, commission)
