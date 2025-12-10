# Issues and bugs to fix, features to implement

## Bugs for immediate fix
- [x] Hand pointer when hovering anywhere on a booking
- [x] Dates on dashboard wrong (checkout day + 1)
- ~[x] Dates on popup wrong (checking day -1)~
- [x] Dates on popups not properly fixed: now display the string "Invalid Date" for both checkin and checkout, although no error appeared during refresh
- [x] Reduce day column minimum width not properly implemented: the dashboard is now only optimized for smaller screens (huge property column on big screen) instead of being only optimized for big screen as before, it should be responsive and fill most available space with the actual calendar. The dashboard should fit full month on 1360x768 screen, but take advantage of bigger screens for clear display.
- [x] On smaller screens, the shown period should be adjusted dynamically to best fit the screen width (2 weeks, 1 week instead of 1 month). The simplle navigation button must be adjusted too to switch to the next/previous displayed period (plus or minus 1 month by default, or 1 or 2 weeks according to the current display). Double arrow navigation remains unchanged as plus or minus 1 year.
- [x] Today button links include current date in URL, it should include no date (default is today) or a key word like "today", otherwise it could create confusion when bookmarking a page.
- [x] Bookings ending after the current displayed period still overflow the calendar, creating an horizontal scrollbar and making the right arrow invisible.
- [x] Mobile week display overflow, move property/unit small title as additional above the row instead of in a specific column.
- [ ] ⚠️ IMPORTANT: bookings deleted from source calendar are not deleted locally. Only ongoing or future bookings should be deleted if they vanish from sources. **IMPLEMENTED, Not yet successful**
    - [ ] ⚠️ IMPORTANT: bookings reactivated from source calendar are still marked as deleted
- [ ] ⚠️ REGRESSION: autosync does not work anymore, manual sync with php artisan sync still working
    - [ ] Sync interval is not enforced, or not properly. E.g. with SYNC_INTERVAL=300 in .env, last sync at 20:26:15, loading a page at 20:37:50 should trigger an immediate sync but nothing happens
- [x] Navigation must be limited to a maximum, customizable in config (no limit for past, maximum 2 years in the future by default)
- [ ] Border radius should only apply to actual begin or end of the bookings. If a booking starts or ends before the current displayed period, the truncated border should have no border radius.
- [ ] WP authentication works with some websites and some not, results to Invalid credentials error.

## Enhancements and new features

- [x] Internal cron tasks management (WordPress-style auto-sync)
- [-] Implement user authentication
    - [x] Through WordPress API
    - [ ] Avoid double login with WP API
    - [ ] Internal auth
    - [ ] Alternative auth (OAuth, OpenID Connect...)
- [ ] Manual sync trigger for admin users
- [ ] Force sync before booking confirmation (when creating/modifying bookings)
- [ ] Timezone support: must be set in config, and taken in account for ics imports (do not use browser timezone, the valid one is the one of the rental location)
- [ ] Highlight week-end columns
- [ ] Make sure all texts are using a localization functions (like gettext _() etc. or the most suitable for Laravel), for future translations of the interface.
- [ ] ICS includes description with sometimes valuable info, they should appear in the popup.
- [ ] "Property" word is wrongly used. "Gîtes Mosaïques" is a property, that includes 5 rental units, "Sun", "Moon", "Violeta", "Zandoli", "Zetoil". "Le lit d'Oli" is another property, which includes only 1 unit (the "lit d'Oli"). This confusion might complicate further plans to allow multiple properties.
- [ ] Multi-tenant support: allow distinct properties, each with their own admins, rental units and calendars
- [ ] Multi-manager support: allow some users to manage multiple selected properties, calendar view show all units together, grouped by property
- [ ] Admin role: admin user can manage any property
- [ ] Unit lifecycle dates: add start_date (unit opening) and end_date (permanent closure) to rental units, distinct from temporary availability blocks
- [ ] Visual marker when in local dev environment (change header color or add a badge) based on hostname/URL (localhost or local network)

## Bugs to fix later
- [ ] Critical database migrations must be detected and executed automatically from the web UI instead of requring command-line access
- [ ] Fresh installation should be possible from both web UI or install script
- [ ] Some overlapping booking are still cut before the border (middle of the day) instead of on the border (for long stays). Booking block with calculation based on first day column creates a multiplied rounding error, making the block not exactly the width it should have.
