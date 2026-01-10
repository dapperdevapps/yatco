#!/bin/bash
# YATCO Sync Lockup Fix Script
# Run this to clear stuck locks and diagnose issues

echo "=== YATCO Sync Lockup Fix Script ==="
echo ""

# Get WordPress root directory (assuming script is in plugin root)
WP_ROOT=$(dirname $(pwd))
if [ ! -f "$WP_ROOT/wp-config.php" ]; then
    # Try one level up
    WP_ROOT=$(dirname $(dirname $(pwd)))
fi

if [ ! -f "$WP_ROOT/wp-config.php" ]; then
    echo "❌ ERROR: Cannot find WordPress root directory"
    echo "Please run this script from your WordPress root or adjust WP_ROOT variable"
    exit 1
fi

echo "WordPress root: $WP_ROOT"
echo ""

# Run diagnostic script if it exists
if [ -f "diagnose-sync.php" ]; then
    echo "Running diagnostics..."
    php diagnose-sync.php
    echo ""
fi

# Ask for confirmation before clearing locks
echo "=== Fix Options ==="
echo "1. Clear stale locks (safe - only clears locks older than 10 minutes)"
echo "2. Clear all locks (force - clears all locks immediately)"
echo "3. Disable auto-resume (stop automatic restarts)"
echo "4. Check and fix stuck processes"
echo ""
read -p "Enter option (1-4): " option

case $option in
    1)
        echo "Clearing stale locks..."
        php -r "
        require '$WP_ROOT/wp-load.php';
        \$lock = get_option('yatco_daily_sync_lock', false);
        if (\$lock !== false) {
            \$age = time() - intval(\$lock);
            if (\$age > 600) {
                delete_option('yatco_daily_sync_lock');
                delete_option('yatco_daily_sync_process_id');
                echo \"✅ Cleared stale lock (age: {\$age}s)\\n\";
            } else {
                echo \"⚠️ Lock is recent (age: {\$age}s), not clearing\\n\";
            }
        } else {
            echo \"✅ No lock found\\n\";
        }
        "
        ;;
    2)
        echo "Clearing ALL locks..."
        php -r "
        require '$WP_ROOT/wp-load.php';
        delete_option('yatco_daily_sync_lock');
        delete_option('yatco_daily_sync_process_id');
        echo \"✅ All locks cleared\\n\";
        "
        ;;
    3)
        echo "Disabling auto-resume..."
        php -r "
        require '$WP_ROOT/wp-load.php';
        delete_option('yatco_daily_sync_auto_resume');
        delete_option('yatco_last_daily_sync_resume_time');
        echo \"✅ Auto-resume disabled\\n\";
        "
        ;;
    4)
        echo "Checking for stuck processes..."
        php -r "
        require '$WP_ROOT/wp-load.php';
        \$lock_pid = get_option('yatco_daily_sync_process_id', false);
        if (\$lock_pid !== false && function_exists('posix_kill')) {
            \$exists = @posix_kill(intval(\$lock_pid), 0);
            if (!\$exists) {
                echo \"❌ Process {\$lock_pid} does not exist - clearing lock\\n\";
                delete_option('yatco_daily_sync_lock');
                delete_option('yatco_daily_sync_process_id');
            } else {
                echo \"⚠️ Process {\$lock_pid} is still running\\n\";
            }
        } else {
            echo \"✅ No process ID found or posix functions not available\\n\";
        }
        "
        ;;
    *)
        echo "Invalid option"
        exit 1
        ;;
esac

echo ""
echo "=== Done ==="

