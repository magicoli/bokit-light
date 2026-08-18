# BOKIT - Booking Orchestration Kit

**Bring On Kitsch Island Time.**

Indie-grade scheduling for serious sunshine.

![Version](https://img.shields.io/badge/Version-1.1.0-lightgrey)
[![Stable](https://img.shields.io/badge/Stable-1.0.0-blue)](CHANGELOG.md)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-12-ff2d20)
[![License](https://img.shields.io/badge/License-AGPL--3.0--or--later-green)](LICENSE)

## Description

For indie property owners who want full calendars without full-time headaches. BOKIT centralizes
bookings, syncs channels, and flags conflicts before they cost you nights. Brilliant orchestration.
Zero busywork.

## Features

Centralized holiday rental booking calendar, synced with PMS, OTA platforms and iCal feeds.

- **One calendar, every unit** — grouped by property, week or month, readable on a phone
- **Kept in step with your platforms** — Beds24, the bookings taken on your own WordPress site, and
  anything publishing an iCal feed
- **One booking, however many platforms report it** — no duplicates, and what you change here
  survives the next sync
- **Group reservations** — several units, one reservation, priced once
- **The money in plain sight** — price, deposit, payments, balance, and the invoice detail when the
  platform sends it
- **Rates and pricing** — per unit and per period, with parent rates, minimum stay and coupons
- **Who sees what** — owners see their own properties, managers and administrators see everything

[ABOUT.md](ABOUT.md) tells the same story at greater length; [CHANGELOG.md](CHANGELOG.md) records
what shipped when.

## Installation

```bash
git clone https://github.com/magicoli/bokit-light.git
cd bokit-light
composer run setup
```

Point your web server at `public/` and open the site: the first visit runs a guided setup in the
browser — administrator account, properties, units — with no further command line.

Requirements, upgrades, web server configuration and hardening recommendations are in
[INSTALLATION.md](INSTALLATION.md).

## Usage

Everything happens in the admin panel at `/admin`: properties, units, their sources, bookings,
rates and users. The calendar lives at `/calendar`.

Channel credentials belong to each property and unit, not to a configuration file — a Beds24 API
key is entered on the property that uses it, and every unit declares the sources it is listed on,
in the order it wants them applied.

Synchronisation runs by itself while the site is used, with no server task to set up. To run it by
hand, or from a scheduler:

```bash
php artisan bokit:sync
```

## Contributing

Conventions, architecture and workflow are in [DEVELOPERS.md](DEVELOPERS.md) — read it before
opening a pull request. In short: English everywhere, Laravel and Filament features before custom
code, tests with every change.

Bugs and ideas go through the tracker in the app itself, which promotes what belongs there to
GitHub issues.

## License

AGPL-3.0-or-later — see [LICENSE](LICENSE).

Copyleft, chosen for what this is: software people run as a service. Where most licences only ask
something of you when you hand the code to someone, the AGPL also asks it when you offer the
software over a network — run a modified version for others, and they are entitled to its source.
Use it, change it, host it; leave the next person the same freedom.
