# Changelog

## 1.0.0 Initial release 594f325d

Centralized holiday rental booking calendar, synced with PMS, OTA platforms and iCal feeds.

- feat: platform synchronisation
  - Beds24 (dual sync) - supports grouped reservations
  - WooCommerce Booking plugin (read)
  - HBook WordPress plugin (read)
  - Multipass WordPress plugin (read, historical integration of an obscure plugin, deprecated)
  - iCal feed (e.g. Airbnb, Booking.com and the rest) (read)
  - different source stays reconciliation in one booking
  - local changes (notes, name, coordinates, etc.) preserved from future syncs overridden by platform data
  - bookings disappeared at the source are flagged as vanished rather than silently dropped
  - synchronisation runs by itself while the site is used, with no server task to set up
- feat: multi-calendar
  - every unit on one screen, grouped by property
  - grouped bookings (same booking for several units)
  - week and month views, responsive for phones, tablets and desktops
  - colours by booking status and by payment state
  - click a stay opens a popup with guests, dates, amounts and a direct link to the original booking on the platform it came from
- feat: booking list
  - search, and filters by unit, period and status
  - a group reservation spanning several units reads as one line
  - a booking view with everything known about the stay
- feat: amounts
  - price, deposit, payments and balance
  - the invoice detail when the platform provides it
- feat: rates and pricing
  - rates per unit and per period, parent rates for shared formulas
  - minimum stay
  - coupons
  - a price calculator
- feat: properties and units
  - each unit declares the platforms it is listed on
  - a public page per property and per unit
- feat: who sees what
  - administrators and managers see every property
  - owners see only theirs, and an owner with a single unit lands straight on it
- feat: dashboard with ongoing and upcoming stays at a glance
- feat: web installation wizard, no command line needed
- feat: English and French localization
- feat: PWA installable on phone and desktop, and readable offline
- feat: bookings export to CSV, with channel, price, commission and guest counts
