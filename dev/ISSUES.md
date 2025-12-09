- [x] Hand pointer when hovering anywhere on a booking
- [x] Dates on dashboard wrong (checkout day + 1)
- ~[x] Dates on popup wrong (checking day -1)~
- [x] Dates on popups not properly fixed: now display the string "Invalid Date" for both checkin and checkout, although no error appeared during refresh
- [x] Reduce day column minimum width not properly implemented: the dashboard is now only optimized for smaller screens (huge property column on big screen) instead of being only optimized for big screen as before, it should be responsive and fill most available space with the actual calendar. The dashboard should fit full month on 1360x768 screen, but take advantage of bigger screens for clear display.
- [x] On smaller screens, the shown period should be adjusted dynamically to best fit the screen width (2 weeks, 1 week instead of 1 month). The simplle navigation button must be adjusted too to switch to the next/previous displayed period (plus or minus 1 month by default, or 1 or 2 weeks according to the current display). Double arrow navigation remains unchanged as plus or minus 1 year.
- [x] Today button links include current date in URL, it should include no date (default is today) or a key word like "today", otherwise it could create confusion when bookmarking a page.
- [x] Bookings ending after the current displayed period still overflow the calendar, creating an horizontal scrollbar and making the right arrow invisible.
- [x] Mobile week display overflow, move property/unit small title as additional above the row instead of in a specific column.
- [ ] Navigation must be limited to a maximum, customizable in config (no limit for past, maximum 2 years in the future by default)
- [ ] Border radius should only apply to actual begin or end of the bookings. If a booking starts or ends before the current displayed period, the truncated border should have no border radius.

## Imrovements
- [-] Implement user authentication
    - [x] Through WordPress API
    - [ ] Internal auth
    - [ ] Alternative auth (OAuth, OpenID Connect...)
- [ ] Internal cron tasks management
- [ ] Timezone support: must be set in config, and taken in account for ics imports (do not use browser timezone, the valid one is the one of the rental location)
- [ ] Highlight week-end columns
- [ ] Make sure all texts are using a localization functions (like gettext _() etc. or the most suitable for Laravel), for future translations of the interface.
- [ ] ICS includes description with sometimes valuable info, they should appear in the popup.
- [ ] "Property" word is wrongly used. "Gîtes Mosaïques" is a property, that includes 5 rental units, "Sun", "Moon", "Violeta", "Zandoli", "Zetoil". "Le lit d'Oli" is another property, which includes only 1 unit (the "lit d'Oli"). This confusion might complicate further plans to allow multiple properties.

## Bugs to fix later
- [ ] Some overlapping booking are cut before the border (middle of the day) instead of on the border (Bouquin/Moon/15-01 to 08-02). Booking block with calculation based on first day column creates a multiplied rounding error, making the block not exactly the width it should have.
