# Auto-Sync: How It Works

## Overview

Bokit uses Laravel's `dispatchAfterResponse()` method for automatic iCal synchronization. This is a **WordPress-style** approach where background tasks run after the HTTP response is sent to the user.

## How it works

1. **User loads a page** → Normal page rendering
2. **Middleware checks** → Is it time to sync? (based on interval)
3. **Response sent** → User gets their page immediately ✅
4. **Sync runs** → After response is sent, the sync job executes
5. **No blocking** → User never waits for external API calls

## Architecture

```
User Request
     |
     v
AutoSync Middleware (checks interval)
     |
     v
Page Response Sent to User ← User gets page fast!
     |
     v
AutoSyncIcal Job Executes ← Happens after response
     |
     v
External iCal APIs Called
```

**Key Point**: No external worker process needed! It's all handled within the web request, just like WordPress cron.

## Configuration

### Sync Interval

The sync interval is stored in the database:

```bash
php artisan tinker
>>> App\Support\Options::set('sync.interval', 300); // 5 minutes
>>> App\Support\Options::get('sync.interval');
=> 300
```

Default: 3600 seconds (1 hour)

### No Queue Configuration Needed

Laravel automatically handles `dispatchAfterResponse()` without needing:
- ❌ No queue worker to run
- ❌ No Redis or external services
- ❌ No supervisor configuration
- ✅ Just works out of the box!

## Monitoring

### View sync logs
```bash
tail -f storage/logs/laravel.log | grep -E '\[SyncJob\]|\[AutoSync\]'
```

You'll see:
```
[AutoSync] Sync triggered
[AutoSync] Sync job will run after response
[SyncJob] Starting iCal synchronization
[SyncJob] Synced api.beds24.com {"created":2,"updated":5,...}
[SyncJob] Synchronization completed {"created":10,"updated":15,...}
```

### Force immediate sync

```bash
# Clear the cache timestamp
php artisan tinker
>>> Cache::forget('last_auto_sync');
>>> exit

# Then reload any page, or run manually:
php artisan bokit:sync
```

### Check last sync time

```bash
php artisan tinker
>>> $lastSync = Cache::get('last_auto_sync', 0);
>>> echo date('Y-m-d H:i:s', $lastSync);
```

## Performance Notes

**User Experience:**
- User gets page response in ~50-200ms (normal page load)
- Sync happens AFTER response is sent
- Zero perceived delay for users

**Server Load:**
- One sync per interval (e.g., every 5 minutes)
- Only triggered by actual page visits
- If no visitors, no unnecessary syncs

**Compared to WordPress:**
Same principle as `wp-cron` - triggered by page loads, runs after response.

## How dispatchAfterResponse() Works

From Laravel documentation:

> "Sometimes you may need to dispatch a job but not have it processed until after the current request has finished and a response has been sent to the user. You may accomplish this using the dispatchAfterResponse method."

Technically:
1. Laravel sends the HTTP response to the user
2. Calls `fastcgi_finish_request()` (PHP-FPM) or equivalent
3. Connection to user is closed
4. Job executes in the same PHP process
5. Process terminates when done

**No queues, no workers, pure Laravel magic!** ✨

## Troubleshooting

### Sync not happening?

1. **Check if AutoSync middleware is enabled:**
   ```bash
   grep -n "AutoSync" bootstrap/app.php
   ```
   Should show: `$middleware->append(\App\Http\Middleware\AutoSync::class);`

2. **Check interval:**
   ```bash
   php artisan tinker
   >>> App\Support\Options::get('sync.interval');
   ```

3. **Check last sync time:**
   ```bash
   php artisan tinker
   >>> Cache::get('last_auto_sync');
   ```

4. **Force a sync to test:**
   ```bash
   php artisan tinker
   >>> Cache::forget('last_auto_sync');
   >>> exit
   # Then reload any page
   ```

5. **Check logs:**
   ```bash
   tail -50 storage/logs/laravel.log
   ```

### Still not working?

Run sync manually to check for errors:
```bash
php artisan bokit:sync
```

If manual sync works but auto-sync doesn't, check that:
- Installation is marked complete: `Options::get('install.complete')` should be `true`
- Interval has passed: Check `last_auto_sync` timestamp in cache
- Logs for any errors in `storage/logs/laravel.log`
