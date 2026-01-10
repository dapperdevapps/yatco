# OPcache Lock Contention Fix

## Problem

When the daily sync cron runs, multiple PHP-FPM workers contend for OPcache locks in `/tmp/.ZendSem.*` files. One worker enters a busy loop doing repeated `fcntl(F_GETLK)` calls, causing server lockup.

### Root Cause

1. Multiple FPM workers try to run sync simultaneously
2. Each worker triggers OPcache updates
3. OPcache uses file locks (`/tmp/.ZendSem.*`) for cache updates
4. Workers contend for these locks
5. Lock contention causes busy loops (100% CPU)
6. Workers try to `kill(SIGTERM)` other processes but fail with `EPERM` on shared hosting
7. Loop continues indefinitely, locking up server

## Solution Implemented

### File-Based Locking System

We've implemented a **file-based locking system** that:

1. **Prevents Multiple Workers**: Only ONE FPM worker can acquire the lock at a time
2. **No Busy Loops**: Uses non-blocking locks (`LOCK_NB`) with exponential backoff
3. **Proper Cleanup**: Always releases locks on exit (via shutdown function)
4. **Independent of OPcache**: Uses separate lock files in temp directory

### How It Works

1. **Lock Acquisition** (before any code execution):
   - Tries to acquire file lock with `flock(LOCK_EX | LOCK_NB)`
   - Non-blocking = returns immediately if lock is held
   - NO busy loops

2. **If Lock is Held**:
   - Closes file handle (prevents holding descriptors)
   - Waits with exponential backoff (1s, 2s, 4s, 5s max)
   - Retries after wait period
   - Maximum 30 seconds total wait time

3. **Lock Release**:
   - Always released via `register_shutdown_function()`
   - Even on fatal errors or exceptions
   - Prevents stale locks

### Files Modified

1. **`includes/yatco-file-lock.php`** (NEW):
   - File-based locking functions
   - Exponential backoff to prevent busy loops
   - Stale lock detection (older than 10 minutes)

2. **`yatco-custom-integration.php`**:
   - Daily sync hook acquires file lock FIRST
   - Full import hook acquires file lock FIRST
   - Locks released on completion

## Verification

### Check Lock Files

Lock files are stored in WordPress temp directory (or system temp):
- `yatco_daily_sync.lock`
- `yatco_full_import.lock`

### Check for Lock Contention

```bash
# Check for OPcache lock contention
ls -la /tmp/.ZendSem.* 2>/dev/null | wc -l

# Check if lock files exist
find /tmp -name "yatco_*.lock" -type f

# Check lock file contents
cat /tmp/yatco_daily_sync.lock
```

### Monitor PHP-FPM Workers

```bash
# Watch FPM worker processes during sync
watch -n 1 'ps aux | grep php-fpm | grep -v grep'

# Check worker CPU usage
top -p $(pgrep -d, -f php-fpm)
```

## Testing

### Manual Test

1. Start sync manually
2. Try to start another sync immediately
3. Second sync should wait (not busy loop) and then exit if lock can't be acquired within 30s

### Monitor Logs

Watch for these log entries:
- `"File lock acquired successfully"` - Lock acquired
- `"Lock held by another process, waiting Xs before retry"` - Lock contention (with wait, not busy loop)
- `"Could not acquire file lock after 30s"` - Lock timeout (exits cleanly)

### Verify No Busy Loops

During sync, check CPU usage:
```bash
# Should see steady CPU usage, not 100%
top -p $(pgrep php-fpm)
```

## Additional Safeguards

1. **Exponential Backoff**: Prevents rapid retries
2. **Timeout**: Maximum 30 seconds wait time
3. **Stale Lock Detection**: Clears locks older than 10 minutes
4. **Shutdown Function**: Always releases locks on exit
5. **Handle Closure**: Closes file handles before sleeping to prevent descriptor leaks

## If Issues Persist

### Clear All Locks Manually

```php
<?php
require 'wp-load.php';
require_once 'includes/yatco-file-lock.php';

yatco_clear_file_lock('daily_sync');
yatco_clear_file_lock('full_import');
echo "Locks cleared\n";
```

### Check OPcache Configuration

If OPcache is causing issues, consider:
1. Disabling OPcache for cron jobs (if possible)
2. Increasing OPcache lock timeout
3. Using OPcache in shared memory mode (not file-based)

### Alternative: Disable OPcache for Cron

In `wp-config.php`:
```php
// Disable OPcache for cron jobs
if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
    if ( function_exists( 'opcache_reset' ) ) {
        opcache_reset();
    }
}
```

## Expected Behavior

**BEFORE FIX:**
- Multiple workers start sync
- Workers contend for OPcache locks
- Busy loops (100% CPU)
- Server locks up

**AFTER FIX:**
- Only ONE worker acquires file lock
- Other workers wait with backoff (no busy loops)
- If lock can't be acquired in 30s, worker exits cleanly
- No OPcache contention
- Server remains responsive

