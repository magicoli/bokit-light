# Installing Bokit

Everything needed to get Bokit running on a server, beyond the three commands in the
[README](README.md).

## Requirements

- PHP 8.3 or later
- Composer and Node.js
- A database — SQLite works and is the default; MySQL, MariaDB and PostgreSQL are supported
  through Laravel's own drivers
- A web server able to run PHP: Caddy, nginx or Apache

## Install

```bash
composer run setup
```

That one command installs the PHP and JavaScript dependencies, creates `.env` from the example,
generates the application key, runs the migrations and builds the front-end assets.

Then point your web server at the `public/` directory and open the site. The first visit runs a
guided setup in the browser — administrator account, properties, units — with no further command
line.

## Upgrading

```bash
git pull
composer install --no-dev
npm install && npm run build
php artisan migrate --force
```

Migrations are part of an upgrade, not an option: the application expects the schema and the data
shape they produce. **Back up the database before running them.** Nothing rolls back a data
migration for you.

## Web server

Bokit serves no URL ending in `.php`. Answering 404 to those at the web server, before PHP is ever
started, costs nothing and removes the entire family of paths vulnerability scanners probe —
`/shell.php`, `/adminfuns.php`, `/admin.php` and their variants. In Caddy:

```caddy
@php path *.php
respond @php 404
```

The equivalent exists in nginx (`location ~ \.php$ { return 404; }`, placed before the FastCGI
block that serves `index.php`) and in Apache.

## Hardening

These are recommendations. Bokit cannot enforce them, and every host does them differently — but
a site reachable from the internet gets scanned, whatever its size.

**Ban repeat offenders at the firewall.** [fail2ban](https://github.com/fail2ban/fail2ban) reading
your web server's access log is the canonical answer: a handful of 404s from one address earns a
ban, and the packets stop arriving. This matters more than any application-level filter, because
an application-level block still costs a full framework boot for every request it rejects, while a
firewall drop costs nothing.

**Rate limiting is already built in.** The public pages answer 429 above sixty requests a minute
per address, and every rejection is logged with the URL, the address and the user agent. That
protects against a visitor or a script hammering a legitimate page — it is not a substitute for
the two measures above, which is why it sits at a deliberately generous limit.

**Keep the log under control.** Logs rotate daily out of the box and `LOG_DAILY_DAYS` (14 by
default) decides how many are kept, so the only thing left to set is how much gets written:
`LOG_LEVEL` in `.env`, where `error` or `warning` suits a production site. Left at its default,
every debug line is written — which is how a log file reaches a gigabyte without anyone noticing.

## Environment

Only a few values are needed beyond what `composer run setup` writes:

```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

`APP_DEBUG` governs how much detail an error page shows. It has nothing to do with what reaches
the log, which is `LOG_LEVEL` — leaving that at its default writes every debug line in production.

Beds24, WordPress and other channel credentials are not set here: they belong to each property and
unit, and are entered in the admin panel.

## Working on the code

See [DEVELOPERS.md](DEVELOPERS.md).
