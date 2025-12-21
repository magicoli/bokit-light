# Auto-Update System

## Architecture

Bokit uses a WordPress-style auto-update system where database migrations are automatically detected and executed through the web interface. No terminal commands needed!

## How It Works

### 1. Migration Detection (`ApplyMigrations` Middleware)

The `ApplyMigrations` middleware runs on every request (except `/install` and `/update` routes) and:

1. Checks if there are pending Laravel migrations
2. Compares migration files in `database/migrations/` with the `migrations` table
3. If pending migrations found → redirects to `/update`
4. Otherwise → allows request to continue normally

**Performance**: Very fast check - only reads file list and queries one database table.

### 2. Update Page (`/update`)

When pending migrations are detected:

1. User is redirected to `/update` page
2. Page shows:
   - List of pending migrations
   - "Run Update Now" button
3. User clicks button → AJAX call to `/update/execute`
4. Migrations run via `Artisan::call('migrate', ['--force' => true])`
5. Success → "Continue to Calendar" button appears
6. User continues to app

### 3. Migration Files

Laravel migrations live in `database/migrations/` with timestamp-based naming:

```
database/migrations/
  2025_12_11_100000_add_status_to_bookings.php
  2025_12_11_110000_add_metadata_fields.php
  ...
```

Each migration has:
- `up()` method: applies changes
- `down()` method: reverts changes (for rollbacks)

## Creating New Migrations

### Step 1: Create Migration File

```bash
# Naming format: YYYY_MM_DD_HHMMSS_description.php
# Example:
touch database/migrations/2025_12_11_120000_add_price_to_bookings.php
```

### Step 2: Write Migration Code

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

### Step 3: Test Locally

The next time you load any page in the app:
1. `ApplyMigrations` middleware detects the new migration
2. You're redirected to `/update`
3. Click "Run Update Now"
4. Migration executes
5. Continue to app

### Step 4: Deploy

Push to production and the same auto-update flow happens for your users!

## Migration Best Practices

### DO:
- ✅ Use descriptive migration names
- ✅ Always provide `down()` method for rollbacks
- ✅ Test migrations locally before deploying
- ✅ Use transactions when modifying data
- ✅ Make migrations idempotent (safe to run multiple times)

### DON'T:
- ❌ Delete migration files after they've been run
- ❌ Modify migrations that have already been deployed
- ❌ Use `php artisan migrate` manually (use web interface)
- ❌ Skip the `down()` method

## Migration Squashing (Future)

When you accumulate many migrations (e.g., 50+ files), Laravel provides a "squash" command:

```bash
php artisan schema:dump
```

This creates a single SQL dump of the entire schema, allowing you to delete old migrations while preserving the ability to create fresh databases.

**Note**: This is a manual operation for cleanup, not part of the auto-update flow.

## Files

### Core Files
- `app/Http/Middleware/ApplyMigrations.php` - Detects pending migrations
- `app/Http/Controllers/UpdateController.php` - Handles `/update` page and execution
- `resources/views/update.blade.php` - Update page UI
- `routes/web.php` - Routes for `/update` and `/update/execute`
- `bootstrap/app.php` - Registers `check.updates` middleware alias

### Migration Files
- `database/migrations/*.php` - All database migrations

### Database Tables
- `migrations` - Tracks which migrations have been run (managed by Laravel)

## User Experience

### Scenario 1: Regular User
1. Opens app → loads normally (no updates pending)
2. New version deployed with migrations
3. Next page load → redirected to `/update`
4. Clicks "Run Update Now" → migrations execute
5. Clicks "Continue to Calendar" → back to normal

**Total time**: ~5-10 seconds

### Scenario 2: Multiple Updates
1. User hasn't opened app in weeks
2. Multiple migrations accumulated
3. Opens app → redirected to `/update`
4. Sees list of all pending updates
5. Clicks "Run Update Now" → ALL migrations execute in order
6. Continues to calendar

**Total time**: ~10-30 seconds (depending on migrations)

## Error Handling

### Migration Fails
1. Error caught by `UpdateController::execute()`
2. User sees error message with details
3. "Run Update Now" button remains active
4. User can retry or contact support

### Recovery
If a migration fails:
1. Check logs: `storage/logs/laravel.log`
2. Fix issue (code or database)
3. User retries via web interface

## Comparison to Manual Migrations

| Aspect | Manual (❌) | Auto-Update (✅) |
|--------|---------|------------|
| Terminal access needed | YES | NO |
| User intervention | YES | Minimal (1 click) |
| Production-friendly | NO | YES |
| Works on shared hosting | NO | YES |
| Rollback support | Manual | Built-in |
| User experience | Bad | Excellent |

## WordPress Comparison

This system is similar to WordPress plugin/theme updates:
- Auto-detection of updates
- One-click update via web interface
- Graceful handling of errors
- No server access required

## Technical Details

### Middleware Execution Order
```
1. CheckInstalled (redirects to /install if needed)
2. Auth (WordPress or None)
3. ApplyMigrations (redirects to /update if needed)
4. AutoSync (syncs iCal feeds)
5. Route Controller
```

### Migration Detection Algorithm
```php
function hasPendingMigrations(): bool {
    $files = glob(database_path('migrations/*.php'));
    $ran = DB::table('migrations')->pluck('migration')->toArray();
    
    foreach ($files as $file) {
        $name = basename($file, '.php');
        if (!in_array($name, $ran)) {
            return true; // Found pending migration
        }
    }
    
    return false; // All migrations run
}
```

**Performance**: O(n) where n = number of migration files. Typically <10ms.

## Future Enhancements

### Automatic Backups
Before running migrations, automatically:
1. Backup database to `storage/backups/`
2. Run migrations
3. If error → restore backup

### Migration Changelog
Store migration metadata:
- Date/time run
- User who triggered
- Success/failure status
- Execution time

### Rollback UI
Web interface for rolling back migrations:
- `/update/rollback` page
- Shows recent migrations
- Click to rollback to specific point

### Version Numbers
Instead of checking migrations table, use semantic versioning:
- Current: `Options::get('app.version')` = "1.2.3"
- Check against hard-coded `APP_VERSION` constant
- Cleaner UI: "Update to v1.3.0"

## Debugging

### Check Pending Migrations
```bash
# Via Tinker
php artisan tinker
>>> DB::table('migrations')->pluck('migration')->toArray();
```

### Force Run Update
```bash
# Via browser
curl -X POST http://localhost:8080/update/execute \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: your-token"
```

### Clear Migrations Table (Caution!)
```bash
php artisan tinker
>>> DB::table('migrations')->truncate();
```

## Summary

The auto-update system ensures:
- ✅ No terminal access needed for updates
- ✅ User-friendly one-click updates
- ✅ Production-safe deployment
- ✅ Graceful error handling
- ✅ Laravel best practices
- ✅ WordPress-style simplicity

Perfect for Oli's "application web autonome" requirement! 🎯
