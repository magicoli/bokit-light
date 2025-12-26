# Auto-Migration System

## Overview

Bokit uses an **automatic migration system** where database migrations are detected and executed automatically on application load, without any user interaction. This is a core design principle for a standalone web application that must work without terminal access.

## Why Automatic Migrations?

### Standalone Application Requirement

Bokit is designed to be deployed anywhere - shared hosting, managed WordPress environments, simple VPS - places where users may not have SSH/terminal access or technical knowledge to run commands.

**Key Principles:**
- ✅ **Zero terminal dependency** - Everything must work through the web interface
- ✅ **Self-healing** - App detects and fixes its own database state
- ✅ **Production-safe** - No manual intervention means no human error
- ✅ **User-agnostic** - Non-technical users can update by uploading files

### Traditional Approach (What We Avoid)

```bash
# ❌ Requires terminal access
ssh user@server
cd /var/www/app
php artisan migrate
```

This fails for:
- Shared hosting users (no SSH)
- Non-technical property managers
- WordPress environments with restricted access
- Managed hosting with limited shell access

### Bokit Approach

```
1. Upload new code (rsync/FTP/git pull)
2. Load any page
3. ✅ Migrations run automatically
```

No commands. No terminal. No user action needed.

## How It Works

### 1. Detection (`ApplyMigrations` Middleware)

The `ApplyMigrations` middleware runs on **every request** (except `/install`) and:

1. Checks for pending migrations by comparing:
   - Files in `database/migrations/`
   - Records in `migrations` table
2. If pending migrations found → **executes them immediately**
3. Logs success/failure
4. Request continues normally

**Performance:** Fast check (~5-10ms) - only filesystem scan and one DB query.

**Location:** `app/Http/Middleware/ApplyMigrations.php`

### 2. Silent Execution

When migrations are detected:
- They execute **immediately and silently**
- No page redirect
- No user confirmation
- No "update available" message

**Why silent?**
- Migrations are **required** database updates, not optional features
- They must run before any code executes (code depends on DB structure)
- Asking users to "confirm" is meaningless - they have no choice
- Automatic execution = zero downtime, zero user confusion

### 3. Error Handling

If a migration fails:
- Error is caught and logged to `storage/logs/laravel.log`
- Admin users see maintenance mode with error details
- Regular users see generic maintenance message
- Fix issue → reload page → migration retries

## Creating Migrations

### Always Use Laravel's Command

```bash
# ✅ CORRECT - Use Laravel's make:migration command
php artisan make:migration add_price_to_bookings

# Creates: database/migrations/2025_12_26_153045_add_price_to_bookings.php
```

**Never create manually:**
```bash
# ❌ WRONG - Manual creation
touch database/migrations/2025_12_11_120000_add_price_to_bookings.php
```

**Why?**
- Laravel generates proper timestamp (YYYY_MM_DD_HHMMSS)
- Includes boilerplate `up()` and `down()` methods
- Guarantees correct execution order
- Prevents timestamp collisions

**Historical Note:** Existing migrations have 4 different formats because they were created manually or by different assistants. All new migrations MUST use `php artisan make:migration`.

### Migration Template

Laravel generates this structure:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
```

### Testing Migrations Locally

**Development workflow:**

```bash
# 1. Create migration
php artisan make:migration add_status_to_bookings

# 2. Edit the generated file
# 3. Load any page in browser
# 4. Migration runs automatically
# 5. Verify in logs or database

# Check migration status
php artisan migrate:status
```

**Never run migrations manually:**
```bash
# ❌ NEVER DO THIS
php artisan migrate
```

Let the application handle migrations automatically, just like production will.

### Deploying Migrations

**Production deployment:**

```bash
# 1. Upload new code (choose one method):
rsync -av --exclude storage --exclude .env ./ user@server:/var/www/app/
# OR
git pull origin main

# 2. That's it!
# Next page load will detect and run migrations automatically
```

## Migration Best Practices

### DO ✅

- **Use `php artisan make:migration`** for all migrations
- **Write descriptive names** - `add_price_to_bookings` not `update_bookings`
- **Always include `down()` method** - for rollback capability
- **Test locally first** - verify migration works before deploying
- **Make idempotent** - safe to run multiple times (use `if (!Schema::hasColumn(...))`)
- **Use transactions** - wrap data updates in `DB::transaction()`
- **Keep focused** - one logical change per migration

### DON'T ❌

- **Never delete migrations** that have been run in production
- **Never modify deployed migrations** - create new ones instead
- **Never run `php artisan migrate` manually** - let app handle it
- **Don't skip `down()` method** - required for proper rollbacks
- **Don't use raw SQL** unless absolutely necessary - use Schema builder
- **Don't assume order** - migrations should be independent

## Architecture

### Middleware Stack

```
Request
  ↓
ApplyMigrations ← Runs migrations if needed
  ↓
CheckInstalled ← Redirects to /install if not installed
  ↓
Auth ← Authentication check
  ↓
AutoSync ← iCal sync (dispatchAfterResponse)
  ↓
Route Controller
  ↓
Response
```

### Files & Directories

**Core Components:**
- `app/Http/Middleware/ApplyMigrations.php` - Migration detection and execution
- `app/Http/Controllers/UpdateController.php` - Error handling and logs (future use)
- `database/migrations/*.php` - All migration files

**Database:**
- `migrations` table - Tracks executed migrations (managed by Laravel)

**Logs:**
- `storage/logs/laravel.log` - Migration execution logs

## Comparison to Manual Approach

| Aspect | Manual Migrations | Auto-Migrations |
|--------|-------------------|-----------------|
| Terminal required | YES ❌ | NO ✅ |
| User action needed | YES ❌ | NO ✅ |
| Works on shared hosting | NO ❌ | YES ✅ |
| Deployment complexity | High ❌ | Low ✅ |
| Error-prone | YES ❌ | NO ✅ |
| Production-friendly | NO ❌ | YES ✅ |
| Zero-downtime | NO ❌ | YES ✅ |

## Future: App Updates (Not Migrations)

**Current Status:** Not implemented

Migrations are **database updates** required by the code. They are automatic and mandatory.

**App updates** would be:
- New feature releases
- Optional upgrades
- Version bumps (1.2.3 → 1.3.0)
- Changelog display
- User notification ("Update available")

This is separate from migrations and not yet implemented. Currently users update by:
- `git pull` (developers)
- `rsync` (production deployments)
- Manual file upload (shared hosting)

## Debugging

### Check Migration Status

```bash
# List all migrations and their status
php artisan migrate:status

# View migrations table
php artisan tinker
>>> DB::table('migrations')->get();
```

### View Logs

```bash
# Watch migration logs in real-time
tail -f storage/logs/laravel.log | grep -i migration
```

### Common Issues

**"Migration already ran but code expects it"**
- Check `migrations` table has the migration record
- Verify migration file exists in `database/migrations/`
- Match filenames exactly

**"Migration fails but keeps retrying"**
- Fix the error in migration code or database
- Reload page - migration will retry automatically
- Check logs for specific error message

**"Want to rollback a migration"**
- Currently must do manually in development
- Production: create new migration to reverse changes
- Never modify deployed migrations

## Summary

Bokit's automatic migration system ensures:

- ✅ **Zero-touch deployment** - Upload code, migrations run automatically
- ✅ **No terminal dependency** - Works on any hosting environment
- ✅ **Production-safe** - Automatic execution eliminates human error
- ✅ **Self-healing** - App maintains its own database state
- ✅ **Laravel best practices** - Uses standard migration framework

This design is essential for a standalone web application that must work everywhere, for everyone, without technical expertise.

---

**Last updated:** 2025-12-26
