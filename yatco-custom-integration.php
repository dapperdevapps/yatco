<?php
/**
 * Plugin Name: YATCO Custom Integration
 * Plugin URI: https://github.com/yatco/yatco-integration
 * Description: Fetch selected YATCO vessels into a Yacht custom post type.
 * Version: 3.1
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: yatco
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'YATCO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YATCO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once YATCO_PLUGIN_DIR . 'includes/yatco-cpt.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-api.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-admin-helper.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-admin.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-shortcode.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-staged-import.php';


// Register activation hook
register_activation_hook( __FILE__, 'yatco_create_cpt' );

// Register shortcode on init
add_action( 'init', 'yatco_register_shortcode' );

// Schedule Daily Sync on init (if enabled) - but only if not already scheduled
// DISABLED: This was causing infinite loops. Daily sync should only be scheduled:
// 1. When settings are saved (via update_option_yatco_api_settings hook)
// 2. When the sync hook actually runs (via yatco_daily_sync_hook action)
// 3. On plugin activation (should be handled separately if needed)
// add_action( 'init', 'yatco_schedule_next_daily_sync_once' );

// Register update all vessels hook
add_action( 'yatco_warm_cache_hook', 'yatco_warm_cache_function' );

// Register import hooks
add_action( 'yatco_full_import_hook', function() {
    // Check import lock BEFORE starting - prevent multiple imports
    $import_lock = get_option( 'yatco_import_lock', false );
    if ( $import_lock !== false ) {
        $lock_time = intval( $import_lock );
        $lock_age = time() - $lock_time;
        // If lock is older than 10 minutes, assume the import process died and release the lock
        if ( $lock_age > 600 ) {
            yatco_log( "Full Import: Hook triggered but lock expired (age: {$lock_age}s), releasing lock and continuing", 'warning' );
            delete_option( 'yatco_import_lock' );
        } else {
            yatco_log( "Full Import: Hook triggered but import already running (lock age: {$lock_age}s), skipping to prevent duplicate imports", 'info' );
            return; // Another import is already running, don't start a new one
        }
    }
    
    yatco_log( 'Full Import: Hook triggered via server cron', 'info' );
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_log( 'Full Import: Token found, calling import function', 'info' );
        yatco_full_import( $token );
    } else {
        yatco_log( 'Full Import: Hook triggered but no token found', 'error' );
    }
} );

// Register daily sync hook
add_action( 'yatco_daily_sync_hook', function() {
    // Check if Daily Sync is enabled
    $options = get_option( 'yatco_api_settings', array() );
    $enabled = isset( $options['yatco_daily_sync_enabled'] ) ? $options['yatco_daily_sync_enabled'] : 'no';
    
    if ( $enabled !== 'yes' ) {
        yatco_log( 'Daily Sync: Hook triggered but Daily Sync is disabled in settings', 'info' );
        return;
    }
    
    yatco_log( 'Daily Sync: Hook triggered via server cron', 'info' );
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_log( 'Daily Sync: Token found, calling sync function', 'info' );
        yatco_daily_sync_check( $token );
        
        // Schedule next run based on frequency
        yatco_schedule_next_daily_sync();
    } else {
        yatco_log( 'Daily Sync: Hook triggered but no token found', 'error' );
    }
} );

/**
 * Schedule the next Daily Sync based on frequency settings.
 */
function yatco_schedule_next_daily_sync() {
    $options = get_option( 'yatco_api_settings', array() );
    $enabled = isset( $options['yatco_daily_sync_enabled'] ) ? $options['yatco_daily_sync_enabled'] : 'no';
    $frequency = isset( $options['yatco_daily_sync_frequency'] ) ? $options['yatco_daily_sync_frequency'] : 'daily';
    
    if ( $enabled !== 'yes' ) {
        // Clear any existing scheduled events if disabled
        wp_clear_scheduled_hook( 'yatco_daily_sync_hook' );
        return;
    }
    
    // Calculate next run time based on frequency
    $next_run = time();
    switch ( $frequency ) {
        case 'hourly':
            $next_run = time() + 3600; // 1 hour
            break;
        case '6hours':
            $next_run = time() + ( 6 * 3600 ); // 6 hours
            break;
        case '12hours':
            $next_run = time() + ( 12 * 3600 ); // 12 hours
            break;
        case 'daily':
            $next_run = time() + ( 24 * 3600 ); // 24 hours
            break;
        case '2days':
            $next_run = time() + ( 2 * 24 * 3600 ); // 2 days
            break;
        case 'weekly':
            $next_run = time() + ( 7 * 24 * 3600 ); // 7 days
            break;
        default:
            $next_run = time() + ( 24 * 3600 ); // Default to daily
    }
    
    // Check if there's already an event scheduled for the same time (within 60 seconds)
    $existing_scheduled = wp_next_scheduled( 'yatco_daily_sync_hook' );
    if ( $existing_scheduled !== false && abs( $existing_scheduled - $next_run ) < 60 ) {
        // Already scheduled for essentially the same time, don't reschedule
        yatco_log( "Daily Sync: Already scheduled for " . date( 'Y-m-d H:i:s', $existing_scheduled ) . " (frequency: {$frequency}), skipping duplicate schedule", 'debug' );
        return;
    }
    
    // Clear existing scheduled events
    wp_clear_scheduled_hook( 'yatco_daily_sync_hook' );
    
    // Schedule next run
    wp_schedule_single_event( $next_run, 'yatco_daily_sync_hook' );
    yatco_log( "Daily Sync: Next run scheduled for " . date( 'Y-m-d H:i:s', $next_run ) . " (frequency: {$frequency})", 'info' );
}

// Schedule Daily Sync when settings are saved
add_action( 'update_option_yatco_api_settings', 'yatco_schedule_next_daily_sync_on_save', 10, 2 );
function yatco_schedule_next_daily_sync_on_save( $old_value, $value ) {
    yatco_schedule_next_daily_sync();
}

// AJAX handler to run Full Import directly (alternative to server cron)
add_action( 'wp_ajax_yatco_run_full_import_direct', 'yatco_ajax_run_full_import_direct' );
function yatco_ajax_run_full_import_direct() {
    check_ajax_referer( 'yatco_run_full_import', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        yatco_log( 'Full Import Direct: Unauthorized access attempt', 'error' );
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    yatco_log( 'Full Import Direct: AJAX handler called', 'info' );
    
    // Increase execution time
    @set_time_limit( 300 );
    @ini_set( 'max_execution_time', 300 );
    yatco_log( 'Full Import Direct: Execution time limit set to 300 seconds', 'info' );
    
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_log( 'Full Import Direct: Token found, starting import', 'info' );
        // Run Full Import
        yatco_full_import( $token );
        yatco_log( 'Full Import Direct: Import function completed', 'info' );
        wp_send_json_success( array( 'message' => 'Full Import completed' ) );
    } else {
        yatco_log( 'Full Import Direct: No API token found', 'error' );
        wp_send_json_error( array( 'message' => 'No API token' ) );
    }
}

// AJAX handler for import status
add_action( 'wp_ajax_yatco_get_import_status', 'yatco_ajax_get_import_status' );
function yatco_ajax_get_import_status() {
    // Note: Nonce check is optional here to allow polling from different tabs
    // We still check user capability for security
    if ( isset( $_POST['_ajax_nonce'] ) ) {
        check_ajax_referer( 'yatco_get_import_status_nonce', '_ajax_nonce', false );
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    // Check if import was stopped - if so, don't return progress data
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    yatco_log( "AJAX Status Check: Stop flag = " . ( $stop_flag !== false ? 'SET (value: ' . $stop_flag . ')' : 'NOT SET' ), 'debug' );
    if ( $stop_flag !== false ) {
        yatco_log( 'AJAX Status Check: Stop flag detected, returning inactive status', 'warning' );
        // Import was stopped - clear any stale progress and return stopped status
        delete_transient( 'yatco_import_progress' );
        delete_transient( 'yatco_daily_sync_progress' );
        wp_cache_delete( 'yatco_import_progress', 'transient' );
        wp_cache_delete( 'yatco_daily_sync_progress', 'transient' );
        
        $response_data = array(
            'active' => false,
            'stage' => null,
            'status' => 'Import stopped by user.',
            'progress' => null,
        );
        yatco_log( 'AJAX Status Check: Sending response with active=false', 'debug' );
        wp_send_json_success( $response_data );
        return;
    }
    
    // Get all progress data - bypass object cache to get fresh data
    wp_cache_delete( 'yatco_import_progress', 'transient' );
    wp_cache_delete( 'yatco_cache_warming_status', 'transient' );
    wp_cache_delete( 'yatco_daily_sync_progress', 'transient' );
    wp_cache_flush(); // Force full cache flush to ensure fresh data
    $import_progress = get_transient( 'yatco_import_progress' );
    $daily_sync_progress = get_transient( 'yatco_daily_sync_progress' );
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    
    yatco_log( 'AJAX Status Check: Import progress = ' . ( $import_progress !== false ? 'EXISTS' : 'NOT FOUND' ), 'debug' );
    yatco_log( 'AJAX Status Check: Daily sync progress = ' . ( $daily_sync_progress !== false ? 'EXISTS' : 'NOT FOUND' ), 'debug' );
    
    // Determine if import is active
    $active_stage = 0;
    $active_progress = null;
    if ( $import_progress !== false && is_array( $import_progress ) ) {
        $active_stage = 'full';
        $active_progress = $import_progress;
        yatco_log( 'AJAX Status Check: Active stage = full, progress data: ' . json_encode( $active_progress ), 'debug' );
    } elseif ( $daily_sync_progress !== false && is_array( $daily_sync_progress ) ) {
        $active_stage = 'daily_sync';
        $active_progress = $daily_sync_progress;
        yatco_log( 'AJAX Status Check: Active stage = daily_sync', 'debug' );
    } else {
        yatco_log( 'AJAX Status Check: No active stage found', 'debug' );
    }
    
    // Return structured data for real-time updates instead of HTML
    $response_data = array(
        'active' => false,
        'stage' => null,
        'status' => $cache_status !== false ? $cache_status : null,
        'progress' => null,
    );
    
    if ( $active_stage === 'full' && $active_progress ) {
        // Get all progress counts
        $processed = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : 0;
        $failed = isset( $active_progress['failed'] ) ? intval( $active_progress['failed'] ) : 0;
        $attempted = isset( $active_progress['attempted'] ) ? intval( $active_progress['attempted'] ) : 0;
        $pending = isset( $active_progress['pending'] ) ? intval( $active_progress['pending'] ) : 0;
        $total_to_process = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
        $total_from_api = isset( $active_progress['total_from_api'] ) ? intval( $active_progress['total_from_api'] ) : $total_to_process;
        $already_imported = isset( $active_progress['already_imported'] ) ? intval( $active_progress['already_imported'] ) : 0;
        
        // Use attempted for progress calculation (shows actual progress through the list)
        $current = $attempted > 0 ? $attempted : $processed;
        $total = $total_to_process;
        // Prefer saved percent if available (more accurate), otherwise calculate
        $percent = isset( $active_progress['percent'] ) ? floatval( $active_progress['percent'] ) : ( $total > 0 ? round( ( $current / $total ) * 100, 1 ) : 0 );
        
        $response_data['active'] = true;
        $response_data['stage'] = 'full';
        $response_data['progress'] = array(
            'processed' => $processed,
            'failed' => $failed,
            'attempted' => $attempted,
            'pending' => $pending,
            'current' => $current,
            'total' => $total,
            'total_from_api' => $total_from_api,
            'already_imported' => $already_imported,
            'percent' => $percent,
            'remaining' => $pending,
        );
        
        // Always include status message in progress object
        if ( $cache_status !== false ) {
            $response_data['progress']['status'] = $cache_status;
        }
        
        // Calculate ETA
        if ( isset( $active_progress['timestamp'] ) && $current > 0 && $total > $current ) {
            $time_elapsed = time() - intval( $active_progress['timestamp'] );
            if ( $time_elapsed > 0 && $current > 0 ) {
                $rate = $current / $time_elapsed; // items per second
                $remaining = $total - $current;
                if ( $rate > 0 ) {
                    $eta_seconds = $remaining / $rate;
                    $response_data['progress']['eta_minutes'] = round( $eta_seconds / 60, 1 );
                }
            }
        }
    } elseif ( $active_stage === 'daily_sync' && $active_progress ) {
        $current = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : 0;
        $total = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
        $percent = $total > 0 ? round( ( $current / $total ) * 100, 1 ) : 0;
        
        $response_data['active'] = true;
        $response_data['stage'] = 'daily_sync';
        $response_data['progress'] = array(
            'current' => $current,
            'total' => $total,
            'percent' => $percent,
            'remaining' => $total - $current,
            'removed' => isset( $active_progress['removed'] ) ? intval( $active_progress['removed'] ) : 0,
            'new' => isset( $active_progress['new'] ) ? intval( $active_progress['new'] ) : 0,
            'price_updates' => isset( $active_progress['price_updates'] ) ? intval( $active_progress['price_updates'] ) : 0,
            'days_on_market_updates' => isset( $active_progress['days_on_market_updates'] ) ? intval( $active_progress['days_on_market_updates'] ) : 0,
        );
    }
    
    yatco_log( 'AJAX Status Check: Sending response - active=' . ( $response_data['active'] ? 'true' : 'false' ) . ', stage=' . ( $response_data['stage'] ?: 'null' ), 'debug' );
    wp_send_json_success( $response_data );
}

// Add auto-resume functionality - check on heartbeat if import needs to continue
add_filter( 'heartbeat_received', 'yatco_heartbeat_received', 10, 2 );
function yatco_heartbeat_received( $response, $data ) {
    // Check if auto-resume is enabled and import is incomplete
    $auto_resume = get_option( 'yatco_import_auto_resume', false );
    if ( $auto_resume !== false ) {
        $import_progress = get_transient( 'yatco_import_progress' );
        if ( $import_progress !== false && is_array( $import_progress ) ) {
            $processed = isset( $import_progress['processed'] ) ? intval( $import_progress['processed'] ) : 0;
            $total = isset( $import_progress['total'] ) ? intval( $import_progress['total'] ) : 0;
            
            // If import is incomplete, trigger resume
            if ( $total > 0 && $processed < $total ) {
                $stop_flag = get_option( 'yatco_import_stop_flag', false );
                $import_lock = get_option( 'yatco_import_lock', false );
                
                // Don't trigger resume if import is already running (lock exists and is recent)
                if ( $import_lock !== false ) {
                    $lock_age = time() - intval( $import_lock );
                    if ( $lock_age < 600 ) { // Lock is less than 10 minutes old
                        // Import is already running, don't trigger another one
                        return $response;
                    }
                }
                
                if ( $stop_flag === false ) {
                    // Check when auto-resume was last triggered to avoid spam
                    $last_resume = get_option( 'yatco_last_auto_resume_time', 0 );
                    $time_since_last_resume = time() - $last_resume;
                    
                    // Only trigger resume if it's been at least 5 seconds since last attempt
                    if ( $time_since_last_resume >= 5 ) {
                        $last_vessel = isset( $import_progress['last_vessel_id'] ) ? $import_progress['last_vessel_id'] : 'unknown';
                        yatco_log( "Full Import: AUTO-RESUME TRIGGERED - Import incomplete ({$processed}/{$total} vessels, last: {$last_vessel}). Scheduling resume...", 'info' );
                        
                        // Schedule resume via wp-cron (non-blocking)
                        wp_schedule_single_event( time() + 2, 'yatco_full_import_hook' );
                        spawn_cron();
                        $response['yatco_auto_resume'] = true;
                        
                        // Update last resume time
                        update_option( 'yatco_last_auto_resume_time', time(), false );
                    }
                }
            } else {
                // Import complete, clear auto-resume
                delete_option( 'yatco_import_auto_resume' );
                delete_option( 'yatco_last_auto_resume_time' );
            }
        }
    }
    
    // Check stop flag first
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    yatco_log( "Heartbeat: Stop flag = " . ( $stop_flag !== false ? 'SET (value: ' . $stop_flag . ')' : 'NOT SET' ), 'debug' );
    if ( $stop_flag !== false ) {
        yatco_log( 'Heartbeat: Stop flag detected, returning inactive status', 'warning' );
        // Import was stopped - return inactive status
        $response['yatco_import_progress'] = array(
            'active' => false,
            'status' => 'Import stopped by user.',
        );
        return $response;
    }
    
    // Add progress data to heartbeat response for real-time updates
    // Force fresh data by bypassing object cache
    wp_cache_delete( 'yatco_import_progress', 'transient' );
    wp_cache_delete( 'yatco_cache_warming_status', 'transient' );
    wp_cache_flush(); // Force full cache flush to ensure fresh data
    $import_progress = get_transient( 'yatco_import_progress' );
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    
    if ( $import_progress !== false && is_array( $import_progress ) ) {
        // Get all progress counts (same structure as AJAX handler)
        $processed = isset( $import_progress['processed'] ) ? intval( $import_progress['processed'] ) : 0;
        $failed = isset( $import_progress['failed'] ) ? intval( $import_progress['failed'] ) : 0;
        $attempted = isset( $import_progress['attempted'] ) ? intval( $import_progress['attempted'] ) : 0;
        $pending = isset( $import_progress['pending'] ) ? intval( $import_progress['pending'] ) : 0;
        $total_to_process = isset( $import_progress['total'] ) ? intval( $import_progress['total'] ) : 0;
        $total_from_api = isset( $import_progress['total_from_api'] ) ? intval( $import_progress['total_from_api'] ) : $total_to_process;
        $already_imported = isset( $import_progress['already_imported'] ) ? intval( $import_progress['already_imported'] ) : 0;
        
        $current = $attempted > 0 ? $attempted : $processed;
        $percent = isset( $import_progress['percent'] ) ? floatval( $import_progress['percent'] ) : ( $total_to_process > 0 ? round( ( $current / $total_to_process ) * 100, 1 ) : 0 );
        
        $progress_data = array(
            'processed' => $processed,
            'failed' => $failed,
            'attempted' => $attempted,
            'pending' => $pending,
            'current' => $current,
            'total' => $total_to_process,
            'total_from_api' => $total_from_api,
            'already_imported' => $already_imported,
            'percent' => $percent,
            'remaining' => $pending,
        );
        
        // Calculate ETA
        if ( isset( $import_progress['timestamp'] ) && $current > 0 && $total_to_process > $current ) {
            $time_elapsed = time() - intval( $import_progress['timestamp'] );
            if ( $time_elapsed > 0 && $current > 0 ) {
                $rate = $current / $time_elapsed; // items per second
                $remaining = $total_to_process - $current;
                if ( $rate > 0 ) {
                    $eta_seconds = $remaining / $rate;
                    $progress_data['eta_minutes'] = round( $eta_seconds / 60, 1 );
                }
            }
        }
        
        $response['yatco_import_progress'] = array(
            'active' => true,
            'stage' => 'full',
            'status' => $cache_status !== false ? $cache_status : null,
            'progress' => $progress_data,
        );
        
        // Also include status in progress for easier access
        if ( $cache_status !== false ) {
            $response['yatco_import_progress']['progress']['status'] = $cache_status;
        }
    } else {
        // No active import
        $response['yatco_import_progress'] = array(
            'active' => false,
        );
    }
    
    return $response;
}

// Increase heartbeat frequency for real-time updates
add_filter( 'heartbeat_settings', 'yatco_heartbeat_settings' );
function yatco_heartbeat_settings( $settings ) {
    // Increase heartbeat frequency to 1 second for real-time updates (when on admin pages)
    if ( is_admin() ) {
        $settings['interval'] = 1; // Update every 1 second for real-time progress
    }
    return $settings;
}

// AJAX handler to get import logs
add_action( 'wp_ajax_yatco_get_import_logs', 'yatco_ajax_get_import_logs' );
function yatco_ajax_get_import_logs() {
    // We still check user capability for security
    if ( isset( $_POST['_ajax_nonce'] ) ) {
        check_ajax_referer( 'yatco_get_import_logs_nonce', '_ajax_nonce', false );
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    // Force fresh logs by bypassing all caches
    wp_cache_delete( 'yatco_import_logs', 'options' );
    wp_cache_flush();
    
    // Get logs directly from database (bypass object cache)
    global $wpdb;
    $logs_serialized = $wpdb->get_var( $wpdb->prepare( 
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        'yatco_import_logs'
    ) );
    
    $logs = array();
    if ( ! empty( $logs_serialized ) ) {
        $logs = maybe_unserialize( $logs_serialized );
        if ( ! is_array( $logs ) ) {
            $logs = array();
        }
    }
    
    // Return all log entries (we store max 100)
    $recent_logs = array_slice( $logs, -50 );
    
    // Format logs for JSON response (reverse order so newest is last)
    $formatted_logs = array();
    foreach ( $recent_logs as $log_entry ) {
        $formatted_logs[] = array(
            'timestamp' => isset( $log_entry['timestamp'] ) ? $log_entry['timestamp'] : '',
            'level' => isset( $log_entry['level'] ) ? $log_entry['level'] : 'info',
            'message' => isset( $log_entry['message'] ) ? $log_entry['message'] : '',
        );
    }
    
    wp_send_json_success( array( 'logs' => $formatted_logs, 'count' => count( $formatted_logs ) ) );
}

// AJAX handler to stop import
add_action( 'wp_ajax_yatco_stop_import', 'yatco_ajax_stop_import' );
function yatco_ajax_stop_import() {
    check_ajax_referer( 'yatco_stop_import', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    yatco_log( '🛑 IMPORT STOP REQUESTED: Stop requested via AJAX', 'warning' );
    
    // Get current progress before clearing
    $current_progress = get_transient( 'yatco_import_progress' );
    $processed = 0;
    $total = 0;
    if ( $current_progress !== false && is_array( $current_progress ) ) {
        $processed = isset( $current_progress['processed'] ) ? intval( $current_progress['processed'] ) : ( isset( $current_progress['last_processed'] ) ? intval( $current_progress['last_processed'] ) : 0 );
        $total = isset( $current_progress['total'] ) ? intval( $current_progress['total'] ) : 0;
    }
    
    yatco_log( "🛑 IMPORT STOP: Current progress was {$processed}/{$total} vessels", 'warning' );
    
    // Set stop flag using WordPress option (more reliable than transient for direct runs)
    // This persists in the database and is immediately available
    update_option( 'yatco_import_stop_flag', time(), false );
    set_transient( 'yatco_cache_warming_stop', time(), 900 );
    yatco_log( '🛑 IMPORT STOP: Stop flag set in database', 'warning' );
    
    // Cancel any scheduled cron jobs
    $scheduled_full = wp_next_scheduled( 'yatco_full_import_hook' );
    $scheduled_warm = wp_next_scheduled( 'yatco_warm_cache_hook' );
    
    if ( $scheduled_full ) {
        wp_unschedule_event( $scheduled_full, 'yatco_full_import_hook' );
        yatco_log( '🛑 IMPORT STOP: Cancelled scheduled Full Import event', 'warning' );
    }
    if ( $scheduled_warm ) {
        wp_unschedule_event( $scheduled_warm, 'yatco_warm_cache_hook' );
        yatco_log( '🛑 IMPORT STOP: Cancelled scheduled warm cache event', 'warning' );
    }
    
    wp_clear_scheduled_hook( 'yatco_stage1_import_hook' );
    wp_clear_scheduled_hook( 'yatco_stage2_import_hook' );
    wp_clear_scheduled_hook( 'yatco_stage3_import_hook' );
    wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
    
    // Disable auto-resume
    update_option( 'yatco_import_auto_resume', false, false );
    delete_option( 'yatco_last_auto_resume_time' );
    yatco_log( '🛑 IMPORT STOP: Auto-resume disabled', 'warning' );
    
    // Release import lock
    $had_lock = get_option( 'yatco_import_lock', false );
    delete_option( 'yatco_import_lock' );
    delete_option( 'yatco_import_process_id' );
    if ( $had_lock !== false ) {
        yatco_log( '🛑 IMPORT STOP: Import lock released', 'warning' );
    }
    
    // Clear progress and status immediately
    wp_cache_delete( 'yatco_import_progress', 'transient' );
    wp_cache_delete( 'yatco_cache_warming_status', 'transient' );
    wp_cache_delete( 'yatco_daily_sync_progress', 'transient' );
    delete_transient( 'yatco_import_progress' );
    delete_transient( 'yatco_daily_sync_progress' );
    delete_transient( 'yatco_stage1_progress' );
    delete_transient( 'yatco_stage2_progress' );
    delete_transient( 'yatco_stage3_progress' );
    delete_transient( 'yatco_cache_warming_progress' );
    delete_transient( 'yatco_cache_warming_status' );
    wp_cache_flush();
    yatco_log( '🛑 IMPORT STOP: Progress cleared from cache and database', 'warning' );
    
    yatco_log( '🛑 IMPORT STOP COMPLETE: All stop actions completed. Import will stop at next checkpoint.', 'warning' );
    
    wp_send_json_success( array( 'message' => 'Import stopped. Progress cleared.' ) );
}

// Schedule periodic cache refresh if enabled
add_action( 'admin_init', 'yatco_maybe_schedule_cache_refresh' );

// Admin settings page
add_action( 'admin_menu', 'yatco_add_admin_menu' );
add_action( 'admin_init', 'yatco_settings_init' );

// Add Update Vessel button to yacht edit screen
add_action( 'add_meta_boxes', 'yatco_add_update_vessel_meta_box' );
add_action( 'admin_post_yatco_update_vessel', 'yatco_handle_update_vessel' );

// Add meta box for editing detailed specifications
add_action( 'add_meta_boxes', 'yatco_add_detailed_specs_meta_box' );
add_action( 'save_post', 'yatco_save_detailed_specs' );

/**
 * Add custom column to show YATCO link in yacht list table.
 */
function yatco_add_yacht_list_columns( $columns ) {
    // Insert YATCO Link column before Date column
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        if ( $key === 'date' ) {
            $new_columns['yatco_link'] = 'YATCO Link';
        }
        $new_columns[ $key ] = $value;
    }
    if ( ! isset( $new_columns['yatco_link'] ) ) {
        $new_columns['yatco_link'] = 'YATCO Link';
    }
    return $new_columns;
}
add_filter( 'manage_yacht_posts_columns', 'yatco_add_yacht_list_columns' );

/**
 * Display YATCO link in yacht list table column.
 */
function yatco_show_yacht_list_column( $column, $post_id ) {
    if ( $column === 'yatco_link' ) {
        $listing_url = get_post_meta( $post_id, 'yacht_yatco_listing_url', true );
        $mlsid = get_post_meta( $post_id, 'yacht_mlsid', true );
        $vessel_id = get_post_meta( $post_id, 'yacht_vessel_id', true );
        
        // Check if URL is in old format (just ID, not full slug)
        // Old format: https://www.yatco.com/yacht/444215/
        // New format: https://www.yatco.com/yacht/70-rizzardi-motor-yacht-2026-407649/
        $needs_regeneration = false;
        if ( empty( $listing_url ) ) {
            $needs_regeneration = true;
        } else {
            // Check if URL matches old format (just number before the trailing slash, no hyphens)
            // URLs with hyphens are the new format
            if ( preg_match( '#https?://www\.yatco\.com/yacht/(\d+)/?$#', $listing_url, $matches ) ) {
                $needs_regeneration = true;
            } elseif ( strpos( $listing_url, '-' ) === false ) {
                // URL exists but has no hyphens, likely old format
                $needs_regeneration = true;
            }
        }
        
        if ( $needs_regeneration ) {
            // Try to build URL from stored meta
            $length = get_post_meta( $post_id, 'yacht_length_feet', true );
            $builder = get_post_meta( $post_id, 'yacht_make', true );
            $category = get_post_meta( $post_id, 'yacht_sub_category', true );
            if ( empty( $category ) ) {
                $category = get_post_meta( $post_id, 'yacht_category', true );
            }
            if ( empty( $category ) ) {
                $category = get_post_meta( $post_id, 'yacht_type', true );
            }
            $year = get_post_meta( $post_id, 'yacht_year', true );
            
            if ( ! function_exists( 'yatco_build_listing_url' ) ) {
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
            }
            
            if ( ! empty( $mlsid ) || ! empty( $vessel_id ) ) {
                $listing_url = yatco_build_listing_url( $post_id, $mlsid, $vessel_id, $length, $builder, $category, $year );
                // Save the regenerated URL for future use
                if ( ! empty( $listing_url ) ) {
                    update_post_meta( $post_id, 'yacht_yatco_listing_url', $listing_url );
                }
            }
        }
        if ( ! empty( $listing_url ) ) {
            echo '<a href="' . esc_url( $listing_url ) . '" target="_blank" rel="noopener noreferrer" class="button button-small">View on YATCO</a>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}
add_action( 'manage_yacht_posts_custom_column', 'yatco_show_yacht_list_column', 10, 2 );