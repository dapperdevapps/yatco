# YATCO Import Debugging Guide for WHM/cPanel Terminal

## 1. Check Cron Job Status

### View all cron jobs for your user:
```bash
crontab -l
```

### Check if wp-cron.php exists and is accessible:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
ls -la wp-cron.php
```

### Test wp-cron.php directly:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
/usr/bin/php -q wp-cron.php
```

If this runs without errors, the cron job should work.

### Check cron logs:
```bash
# Check system cron logs (varies by system)
grep CRON /var/log/cron
# OR
grep CRON /var/log/syslog
# OR check mail for cron errors
tail -f /var/spool/mail/webhosting
```

## 2. Find the Correct PHP CLI Binary

Since `/usr/bin/php` is php-cgi (doesn't support -r flag), find the actual CLI PHP:

```bash
which php-cli
# OR
whereis php-cli
# OR
find /usr -name php-cli 2>/dev/null
# OR try common locations:
/usr/local/bin/php -v
/opt/cpanel/ea-php*/root/usr/bin/php -v
```

Once you find it, use that path instead of `/usr/bin/php` in all commands below.
Example: If it's `/usr/local/bin/php`, use that.

## 3. Test WordPress and Plugin Loading

### Create a test file to check WordPress:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > test_wp.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
echo 'WordPress loaded successfully' . PHP_EOL;
EOF
/usr/bin/php -q test_wp.php
rm test_wp.php
```

### Check if plugin is loaded:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > test_plugin.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
if (function_exists('yatco_get_token')) {
    echo 'Plugin loaded' . PHP_EOL;
} else {
    echo 'Plugin NOT loaded' . PHP_EOL;
}
EOF
/usr/bin/php -q test_plugin.php
rm test_plugin.php
```

### Check if hooks are registered:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > test_hooks.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
global $wp_filter;
if (isset($wp_filter['yatco_full_import_hook'])) {
    echo 'yatco_full_import_hook is registered' . PHP_EOL;
} else {
    echo 'yatco_full_import_hook is NOT registered' . PHP_EOL;
}
if (has_action('yatco_full_import_hook')) {
    echo 'has_action confirms hook is registered' . PHP_EOL;
} else {
    echo 'has_action says hook is NOT registered' . PHP_EOL;
}
EOF
/usr/bin/php -q test_hooks.php
rm test_hooks.php
```

## 4. Test wp-cron Event Scheduling

### Check if events are scheduled:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > test_scheduled.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
$next = wp_next_scheduled('yatco_full_import_hook');
if ($next) {
    echo 'Next scheduled: ' . date('Y-m-d H:i:s', $next) . ' (' . $next . ')' . PHP_EOL;
    echo 'Current time: ' . date('Y-m-d H:i:s', time()) . ' (' . time() . ')' . PHP_EOL;
    if ($next <= time()) {
        echo 'Event is due and should run' . PHP_EOL;
    } else {
        echo 'Event is scheduled for future' . PHP_EOL;
    }
} else {
    echo 'No yatco_full_import_hook events scheduled' . PHP_EOL;
}
EOF
/usr/bin/php -q test_scheduled.php
rm test_scheduled.php
```

### Manually trigger wp-cron:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > trigger_cron.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
$crons = _get_cron_array();
if (!empty($crons)) {
    echo 'Found ' . count($crons) . ' scheduled events' . PHP_EOL;
    foreach ($crons as $timestamp => $cron) {
        if (isset($cron['yatco_full_import_hook'])) {
            echo 'Found yatco_full_import_hook scheduled for ' . date('Y-m-d H:i:s', $timestamp) . PHP_EOL;
        }
    }
    // Trigger due events
    spawn_cron();
    echo 'Cron triggered' . PHP_EOL;
} else {
    echo 'No cron events found' . PHP_EOL;
}
EOF
/usr/bin/php -q trigger_cron.php
rm trigger_cron.php
```

## 5. Test Direct Hook Execution (IMPORTANT - This will actually run the import!)

### Manually trigger the import hook:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > trigger_import.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
echo 'Triggering yatco_full_import_hook directly...' . PHP_EOL;
do_action('yatco_full_import_hook');
echo 'Hook execution completed' . PHP_EOL;
EOF
/usr/bin/php -q trigger_import.php
rm trigger_import.php
```

**This will actually start the import!** Watch for output/logs.

## 6. Check Import Lock and Status

### Check if import is locked:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > check_lock.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
$lock = get_option('yatco_import_lock', false);
$pid = get_option('yatco_import_process_id', false);
if ($lock) {
    echo 'Import lock exists: ' . $lock . ' (age: ' . (time() - $lock) . 's)' . PHP_EOL;
    echo 'Process ID: ' . ($pid ? $pid : 'none') . PHP_EOL;
} else {
    echo 'No import lock found' . PHP_EOL;
}
EOF
/usr/bin/php -q check_lock.php
rm check_lock.php
```

### Check stop flag:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > check_stop.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
$stop = get_option('yatco_import_stop_flag', false);
if ($stop) {
    echo 'Stop flag is SET: ' . $stop . PHP_EOL;
} else {
    echo 'Stop flag is NOT SET' . PHP_EOL;
}
EOF
/usr/bin/php -q check_stop.php
rm check_stop.php
```

### Check import progress:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > check_progress.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
require_once('wp-content/plugins/yatco/includes/yatco-progress.php');
$progress = yatco_get_import_status('full');
if ($progress) {
    print_r($progress);
} else {
    echo 'No progress data found' . PHP_EOL;
}
EOF
/usr/bin/php -q check_progress.php
rm check_progress.php
```

## 7. Clear Locks and Flags (Use with Caution!)

### Clear import lock:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > clear_locks.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
delete_option('yatco_import_lock');
delete_option('yatco_import_process_id');
delete_option('yatco_import_stop_flag');
delete_option('yatco_import_using_fastcgi');
echo 'Locks and flags cleared' . PHP_EOL;
EOF
/usr/bin/php -q clear_locks.php
rm clear_locks.php
```

## 7. Check PHP Version and Extensions

### Check PHP version:
```bash
/usr/bin/php -v
```

### Check if required extensions are loaded:
```bash
/usr/bin/php -m | grep -E "curl|json|mbstring"
```

## 8. Test API Connection

### Test if API token is configured:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > check_token.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
$token = yatco_get_token();
if ($token) {
    echo 'Token is configured (length: ' . strlen($token) . ')' . PHP_EOL;
} else {
    echo 'Token is NOT configured' . PHP_EOL;
}
EOF
/usr/bin/php -q check_token.php
rm check_token.php
```

## 9. View Recent Logs

### Check plugin logs (if logging to file):
```bash
cd /home/webhosting/public_html/championyachtgroup.com
# Check if logs directory exists
ls -la wp-content/uploads/yatco-logs/ 2>/dev/null || echo "Logs directory not found"

# Or check debug.log if WP_DEBUG_LOG is enabled
tail -50 wp-content/debug.log 2>/dev/null || echo "debug.log not found"
```

## 10. Manual Import Test (Full Debug)

### Run a minimal test to see what happens:
```bash
cd /home/webhosting/public_html/championyachtgroup.com
cat > full_debug.php << 'EOF'
<?php
define('WP_USE_THEMES', false);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
require('wp-load.php');

echo '=== YATCO Import Debug Test ===' . PHP_EOL;
echo 'WordPress loaded: ' . (function_exists('get_option') ? 'YES' : 'NO') . PHP_EOL;
echo 'Plugin loaded: ' . (function_exists('yatco_get_token') ? 'YES' : 'NO') . PHP_EOL;
echo 'Hook registered: ' . (has_action('yatco_full_import_hook') ? 'YES' : 'NO') . PHP_EOL;

$token = yatco_get_token();
echo 'Token configured: ' . ($token ? 'YES (len: ' . strlen($token) . ')' : 'NO') . PHP_EOL;

$lock = get_option('yatco_import_lock', false);
echo 'Import lock: ' . ($lock ? 'YES (age: ' . (time() - $lock) . 's)' : 'NO') . PHP_EOL;

$stop = get_option('yatco_import_stop_flag', false);
echo 'Stop flag: ' . ($stop ? 'YES' : 'NO') . PHP_EOL;

$next = wp_next_scheduled('yatco_full_import_hook');
echo 'Scheduled events: ' . ($next ? date('Y-m-d H:i:s', $next) : 'NONE') . PHP_EOL;

echo PHP_EOL . 'If all above show correct values, try manually triggering the hook:' . PHP_EOL;
echo 'do_action("yatco_full_import_hook");' . PHP_EOL;
EOF
/usr/bin/php -q full_debug.php
rm full_debug.php
```

## 11. QUICK TEST - Run This First!

Run this single command to test everything at once:

```bash
cd /home/webhosting/public_html/championyachtgroup.com && cat > quick_test.php << 'EOFTEST'
<?php
define('WP_USE_THEMES', false);
require('wp-load.php');
echo "1. WordPress: " . (function_exists('get_option') ? 'OK' : 'FAIL') . PHP_EOL;
echo "2. Plugin: " . (function_exists('yatco_get_token') ? 'OK' : 'FAIL') . PHP_EOL;
echo "3. Hook: " . (has_action('yatco_full_import_hook') ? 'OK' : 'FAIL') . PHP_EOL;
echo "4. Token: " . (yatco_get_token() ? 'OK' : 'FAIL') . PHP_EOL;
echo "5. Lock: " . (get_option('yatco_import_lock', false) ? 'EXISTS' : 'NONE') . PHP_EOL;
echo "6. Stop: " . (get_option('yatco_import_stop_flag', false) ? 'SET' : 'NOT SET') . PHP_EOL;
EOFTEST
/usr/local/bin/php quick_test.php && rm quick_test.php
```

**Note:** Use `/usr/local/bin/php` (CLI version) instead of `/usr/bin/php` (php-cgi) for better compatibility.

## 12. Test Your Cron Job Manually

Since your cron runs at :19 and :49, test it manually:

```bash
cd /home/webhosting/public_html/championyachtgroup.com
/usr/local/bin/php wp-cron.php
```

This should execute any scheduled wp-cron events. Watch for output or check logs.

