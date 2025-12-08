# Bokit - Quick Start

## Backend is ready! ✅

You have:
- 5 properties configured (Moon, Sun, Violeta, Zandoli, Zetoil)
- 10 iCal sources synced
- 251 bookings imported

## Start the frontend

```bash
chmod +x dev/start-server.sh
./dev/start-server.sh
```

Then open: **http://localhost:8000**

## What you'll see

- Multi-property calendar view (one row per property)
- All bookings displayed as colored bars
- Click on any booking to see details in a popup
- Navigate between months with Previous/Next buttons

## Sync calendars regularly

```bash
# Manual sync
php artisan bokit:sync

# Setup automatic sync (add to crontab)
* * * * * cd /path/to/bokit-light && php artisan schedule:run >> /dev/null 2>&1
```

## Next steps

Phase 1 is complete! You now have a functional calendar manager.

For Phase 2 features (notes, manual bookings, export), we can add them progressively.
