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
shape they produce. Nothing rolls back a data migration for you, so take a backup first:

```bash
php artisan backup:run
```

## Deploying

`deploy.maml.example` is a working [Deployer](https://deployer.org) recipe: copy it to
`deploy.maml`, put your own host in it, and deploy with `dep deploy`. The real file stays out of
git, since it names a server and its paths.

Two things in it are not decoration. `shared_files` and `shared_dirs` keep the database, the
environment and `storage/` out of the releases, so a rollback does not take the data with it. And
`db:backup`, hooked before `artisan:migrate`, is what makes the guarantee below true — deploying
by other means means arranging that step yourself.

## Backups

Every archive holds the database dump, the per-property settings — which is where the Beds24 and
WordPress credentials live, in JSON files rather than in the database — and `.env`, without which
the encrypted values in the dump cannot be read. Together they weigh next to nothing, and
`php artisan backup:run --essentials` stops there. A full backup — the plain
`php artisan backup:run` — adds the uploaded files, the only part that can grow. The application
code is in neither: it comes from git.

Both commands answer to `bokit:backup` as well, and `backup:clean` to `bokit:backup-clean`: the
name from the package's documentation and the name from `php artisan list` are the same command.

The two kinds are written to two destinations under `storage/backups`, and that separation is the
point: an hourly archive is worth keeping for a day — past that a full one covers the same
ground — while a complete archive is what one wants to find a month later. They are kept across
deploys, and taken:

- **before every deploy** — complete, since a release is not known in advance to change the
  database only, and a failed backup aborts the deploy;
- **before automatic migrations**, when a page load finds the schema out of date — complete too,
  for the same reason;
- **while the site is being used** — the essentials at most once an hour, everything once a day.

That last one needs no setting up: like the synchronisation, it rides on visits, and the work
happens after the response so nobody waits for it. There is no cron entry to add and no queue
worker to keep alive. A day without visitors is a day without changes, and gets no backup.

On an installation whose uploads run to gigabytes, taking that work out of the web process is
worth it: set `BACKUP_AUTO=false` and give the two commands a cron of your own.

```
0 * * * * cd /path/to/bokit && php artisan backup:run --essentials
30 3 * * * cd /path/to/bokit && php artisan backup:run && php artisan backup:clean
```

Full backups are kept for good: every one of the last day, then one a day for a week, one a week
for a month, one a month for a year, and one a year. Essential backups are kept a day. Each figure,
the intervals and the destination are `.env` settings — see `.env.example`. `BACKUP_DISKS` accepts any disk configured in
`config/filesystems.php`, so sending the archives off the machine is a matter of naming an S3 or
SFTP disk there. Do set `BACKUP_ARCHIVE_PASSWORD` if you do: the archive carries `.env`.

Restoring is manual, and deliberately so. Unzip the archive; `db-dumps/` holds the SQL dump to feed
back into the database, and the rest of the tree mirrors the paths the files came from.

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
