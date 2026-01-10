<?php
/**
 * YATCO Daily Sync Diagnostic Script
 * 
 * Run this from terminal to check sync status and identify lockup issues:
 * php diagnose-sync.php
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/wp-load.php' );

echo "=== YATCO Daily Sync Diagnostic ===\n\n";

// Check sync status
require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
$sync_progress = yatco_get_import_status( 'daily_sync' );

echo "1. Sync Progress Status:\n";
if ( $sync_progress !== false && is_array( $sync_progress ) ) {
    $processed = isset( $sync_progress['processed'] ) ? intval( $sync_progress['processed'] ) : 0;
    $total = isset( $sync_progress['total'] ) ? intval( $sync_progress['total'] ) : 0;
    $updated_at = isset( $sync_progress['updated_at'] ) ? intval( $sync_progress['updated_at'] ) : 0;
    $age = time() - $updated_at;
    
    echo "   Processed: {$processed} / {$total} vessels\n";
    echo "   Last updated: " . date( 'Y-m-d H:i:s', $updated_at ) . " ({$age} seconds ago)\n";
    echo "   Status: " . ( isset( $sync_progress['status'] ) ? $sync_progress['status'] : 'unknown' ) . "\n";
    
    if ( $total > 0 && $processed < $total && $age > 300 ) {
        echo "   ⚠️ WARNING: Sync appears stuck (not updated in {$age} seconds)\n";
    }
} else {
    echo "   No active sync progress found\n";
}

// Check locks
echo "\n2. Lock Status:\n";
$sync_lock = get_option( 'yatco_daily_sync_lock', false );
$lock_process_id = get_option( 'yatco_daily_sync_process_id', false );

if ( $sync_lock !== false ) {
    $lock_age = time() - intval( $sync_lock );
    echo "   Lock exists: YES\n";
    echo "   Lock age: {$lock_age} seconds (" . round( $lock_age / 60, 1 ) . " minutes)\n";
    echo "   Lock process ID: " . ( $lock_process_id !== false ? $lock_process_id : 'NONE' ) . "\n";
    
    if ( $lock_age > 600 ) {
        echo "   ⚠️ WARNING: Lock is stale (older than 10 minutes) - sync may have died\n";
    } elseif ( $lock_age > 1800 ) {
        echo "   🚨 CRITICAL: Lock is very stale (older than 30 minutes) - definitely stuck\n";
    }
} else {
    echo "   Lock exists: NO\n";
}

// Check if process is actually running
if ( $lock_process_id !== false ) {
    echo "\n3. Process Check:\n";
    if ( function_exists( 'posix_kill' ) ) {
        // Try to check if process exists (Unix/Linux)
        $is_alive = @posix_kill( intval( $lock_process_id ), 0 );
        echo "   Process {$lock_process_id} exists: " . ( $is_alive ? 'YES' : 'NO (process died)' ) . "\n";
    } else {
        echo "   Cannot check process status (posix functions not available on Windows)\n";
        echo "   Lock process ID: {$lock_process_id}\n";
    }
}

// Check scheduled events
echo "\n4. Scheduled Events:\n";
$next_sync = wp_next_scheduled( 'yatco_daily_sync_hook' );
$next_check = wp_next_scheduled( 'yatco_daily_sync_auto_resume_check' );

if ( $next_sync !== false ) {
    $time_until = $next_sync - time();
    echo "   Next sync: " . date( 'Y-m-d H:i:s', $next_sync ) . " (in " . round( $time_until / 60, 1 ) . " minutes)\n";
} else {
    echo "   Next sync: NOT SCHEDULED\n";
}

if ( $next_check !== false ) {
    $time_until = $next_check - time();
    echo "   Next auto-resume check: " . date( 'Y-m-d H:i:s', $next_check ) . " (in " . round( $time_until / 60, 1 ) . " minutes)\n";
} else {
    echo "   Next auto-resume check: NOT SCHEDULED\n";
}

// Check auto-resume flag
echo "\n5. Auto-Resume Status:\n";
$auto_resume = get_option( 'yatco_daily_sync_auto_resume', false );
echo "   Auto-resume enabled: " . ( $auto_resume !== false ? 'YES (since ' . date( 'Y-m-d H:i:s', intval( $auto_resume ) ) . ')' : 'NO' ) . "\n";

// Check system resources
echo "\n6. System Resources:\n";
if ( function_exists( 'memory_get_usage' ) ) {
    $memory_usage = round( memory_get_usage( true ) / 1024 / 1024, 2 );
    $memory_peak = round( memory_get_peak_usage( true ) / 1024 / 1024, 2 );
    $memory_limit = ini_get( 'memory_limit' );
    echo "   Current memory: {$memory_usage} MB\n";
    echo "   Peak memory: {$memory_peak} MB\n";
    echo "   Memory limit: {$memory_limit}\n";
}

$max_exec_time = ini_get( 'max_execution_time' );
echo "   Max execution time: " . ( $max_exec_time == 0 ? 'Unlimited' : $max_exec_time . ' seconds' ) . "\n";

// Check recent logs
echo "\n7. Recent Log Entries (last 10):\n";
$logs = get_option( 'yatco_import_logs', array() );
if ( ! empty( $logs ) && is_array( $logs ) ) {
    $recent_logs = array_slice( $logs, -10 );
    foreach ( $recent_logs as $log ) {
        $timestamp = isset( $log['timestamp'] ) ? $log['timestamp'] : '';
        $level = isset( $log['level'] ) ? strtoupper( $log['level'] ) : 'INFO';
        $message = isset( $log['message'] ) ? $log['message'] : '';
        echo "   [{$timestamp}] [{$level}] {$message}\n";
    }
} else {
    echo "   No log entries found\n";
}

// Recommendations
echo "\n=== Recommendations ===\n";

if ( $sync_lock !== false ) {
    $lock_age = time() - intval( $sync_lock );
    if ( $lock_age > 600 ) {
        echo "⚠️ Lock is stale. You can clear it manually:\n";
        echo "   DELETE FROM wp_options WHERE option_name = 'yatco_daily_sync_lock';\n";
        echo "   DELETE FROM wp_options WHERE option_name = 'yatco_daily_sync_process_id';\n";
        echo "\n";
    }
}

if ( $sync_progress !== false && is_array( $sync_progress ) ) {
    $processed = isset( $sync_progress['processed'] ) ? intval( $sync_progress['processed'] ) : 0;
    $total = isset( $sync_progress['total'] ) ? intval( $sync_progress['total'] ) : 0;
    
    if ( $total > 5000 ) {
        echo "⚠️ Large sync detected ({$total} vessels). Consider:\n";
        echo "   - Increasing delays between batches\n";
        echo "   - Processing fewer vessels per batch\n";
        echo "   - Running sync during off-peak hours\n";
        echo "\n";
    }
}

if ( function_exists( 'memory_get_peak_usage' ) ) {
    $memory_peak = memory_get_peak_usage( true ) / 1024 / 1024;
    if ( $memory_peak > 400 ) {
        echo "⚠️ High memory usage detected. Consider:\n";
        echo "   - Increasing PHP memory_limit\n";
        echo "   - Processing smaller batches\n";
        echo "\n";
    }
}

echo "\n=== End Diagnostic ===\n";
