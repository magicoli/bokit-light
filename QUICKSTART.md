# Bokit - Quick Start

## What's working ✅

- 5 properties configured (Moon, Sun, Violeta, Zandoli, Zetoil)
- 10 iCal sources synced
- 251 bookings imported
- Horizontal calendar view - one row per property
- Mobile-responsive (auto-switches to week view on mobile)
- Visual booking spans from check-in noon to check-out noon

## Start the server

```bash
chmod +x dev/start-server.sh
./dev/start-server.sh
```

Then open: **http://localhost:8000**

## Navigation

- **← Month / Month →**: Navigate month by month
- **⏪ / ⏩**: Jump one year back/forward
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

# Or setup cron for automatic hourly sync
* * * * * cd /path/to/bokit-light && php artisan schedule:run >> /dev/null 2>&1
```

Then in `app/Console/Kernel.php`, add:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('bokit:sync')->hourly();
}
```

## Notes on old bookings

If old bookings don't appear, it's likely the iCal feeds themselves don't include them (common limitation in Beds24/HBook). The backend doesn't filter by date when syncing - it imports everything the feeds provide.

## Next steps

Phase 1 complete! For Phase 2:
- Notes on bookings
- Manual bookings
- Export consolidated iCal
- Grouped bookings
- Additional fields (guests, price, commission)
