# Fixing YATCO Sync Server Lockup Issues

## Quick Diagnostic Commands

### Windows PowerShell:
```powershell
# Run diagnostic script
powershell -ExecutionPolicy Bypass -File diagnose-sync.ps1

# Or run PHP diagnostic directly
php diagnose-sync.php
```

### Linux/Mac:
```bash
# Run diagnostic script
bash fix-sync-lockup.sh

# Or run PHP diagnostic directly
php diagnose-sync.php
```

## Common Issues & Fixes

### 1. Stale Lock (Sync Stopped but Lock Remains)

**Symptoms:** Sync shows as running but hasn't updated in 10+ minutes

**Fix via PHP:**
```php
php -r "require 'wp-load.php'; delete_option('yatco_daily_sync_lock'); delete_option('yatco_daily_sync_process_id');"
```

**Fix via SQL:**
```sql
DELETE FROM wp_options WHERE option_name = 'yatco_daily_sync_lock';
DELETE FROM wp_options WHERE option_name = 'yatco_daily_sync_process_id';
```

### 2. Auto-Resume Loop (Sync Keeps Restarting)

**Symptoms:** Sync restarts constantly, server load high

**Fix - Disable auto-resume:**
```php
php -r "require 'wp-load.php'; delete_option('yatco_daily_sync_auto_resume'); delete_option('yatco_last_daily_sync_resume_time');"
```

### 3. High Memory Usage

**Symptoms:** Server becomes unresponsive during sync

**Fixes Applied:**
- Reduced batch size from 50 to 25 vessels
- Increased delays between batches (1s → 3s)
- Added memory monitoring - stops if using >80% of memory limit
- Added execution time checks - stops 30s before timeout

**Manual Fix - Increase PHP Memory:**
Edit `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '1024M');
```

### 4. Too Many API Calls

**Symptoms:** API rate limiting, slow responses

**Fixes Applied:**
- Increased delay between vessels (200ms → 500ms)
- Added 10-second pause every 5 minutes of processing
- Reduced batch size

**If still having issues, further increase delays:**
Edit `includes/yatco-staged-import.php`:
- Line ~1811: Change `$batch_size = 25;` to `$batch_size = 10;`
- Line ~1812: Change `$delay_seconds = 3;` to `$delay_seconds = 5;`

### 5. Check Current Status

**Via WordPress Admin:**
- Go to Settings → YATCO API → Status tab
- Check "Import Status & Progress" section

**Via Terminal:**
```bash
php diagnose-sync.php
```

**Via SQL:**
```sql
-- Check lock
SELECT option_name, option_value, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(autoload) as age_seconds 
FROM wp_options 
WHERE option_name IN ('yatco_daily_sync_lock', 'yatco_daily_sync_process_id', 'yatco_daily_sync_auto_resume');

-- Check progress
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name = 'yatco_daily_sync_status';
```

## Prevention Measures Added

1. **Execution Time Protection:** Stops 30 seconds before PHP timeout
2. **Memory Protection:** Stops if memory usage exceeds 80% of limit
3. **Rate Limiting:** Reduced batch sizes and increased delays
4. **Auto-Timeout Detection:** If progress hasn't updated in 10 minutes, clears and restarts fresh
5. **Better Auto-Resume:** Won't restart too frequently (2 minute minimum between attempts)

## Recommended Settings for Large Datasets

If you have 4000+ vessels, consider:

1. **Increase delays in code:**
   - Batch size: 10-15 vessels
   - Delay between batches: 5-10 seconds
   - Delay between vessels: 1000ms (1 second)

2. **Run sync during off-peak hours:**
   - Schedule daily sync for 2-4 AM
   - Lower server load = faster processing

3. **Increase server resources:**
   - PHP memory_limit: 512M or higher
   - max_execution_time: 600 seconds (10 minutes) or 0 (unlimited)

4. **Consider disabling auto-resume temporarily:**
   - If sync keeps causing issues, disable auto-resume
   - Run sync manually during maintenance windows

## Emergency Stop

If sync is completely locked and you can't access admin:

**Via SQL:**
```sql
-- Clear all sync-related locks and flags
DELETE FROM wp_options WHERE option_name IN (
    'yatco_daily_sync_lock',
    'yatco_daily_sync_process_id',
    'yatco_daily_sync_auto_resume',
    'yatco_last_daily_sync_resume_time',
    'yatco_import_stop_flag'
);

DELETE FROM wp_options WHERE option_name LIKE 'yatco_daily_sync_status%';
```

**Via Terminal:**
```bash
php -r "require 'wp-load.php'; 
delete_option('yatco_daily_sync_lock');
delete_option('yatco_daily_sync_process_id');
delete_option('yatco_daily_sync_auto_resume');
delete_option('yatco_import_stop_flag');
delete_transient('yatco_cache_warming_stop');
echo 'All sync locks and flags cleared';"
```

