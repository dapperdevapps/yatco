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

// Register cache warming hook
add_action( 'yatco_warm_cache_hook', 'yatco_warm_cache_function' );

// Register import hooks
add_action( 'yatco_full_import_hook', function() {
    yatco_log( 'Full Import: Hook triggered via WP-Cron', 'info' );
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_log( 'Full Import: Token found, calling import function', 'info' );
        yatco_full_import( $token );
    } else {
        yatco_log( 'Full Import: Hook triggered but no token found', 'error' );
    }
} );

// AJAX handler to run Full Import directly (for when WP-Cron isn't working)
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
    
    // Get all progress data - bypass object cache to get fresh data
    wp_cache_delete( 'yatco_import_progress', 'transient' );
    wp_cache_delete( 'yatco_cache_warming_status', 'transient' );
    $import_progress = get_transient( 'yatco_import_progress' );
    $daily_sync_progress = get_transient( 'yatco_daily_sync_progress' );
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    
    // Determine if import is active
    $active_stage = 0;
    $active_progress = null;
    if ( $import_progress !== false && is_array( $import_progress ) ) {
        $active_stage = 'full';
        $active_progress = $import_progress;
    } elseif ( $daily_sync_progress !== false && is_array( $daily_sync_progress ) ) {
        $active_stage = 'daily_sync';
        $active_progress = $daily_sync_progress;
    }
    
    // Return structured data for real-time updates instead of HTML
    $response_data = array(
        'active' => false,
        'stage' => null,
        'status' => $cache_status !== false ? $cache_status : null,
        'progress' => null,
    );
    
    if ( $active_stage === 'full' && $active_progress ) {
        // Use 'processed' count if available (more accurate), otherwise fall back to 'last_processed'
        $current = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : ( isset( $active_progress['last_processed'] ) ? intval( $active_progress['last_processed'] ) : 0 );
        $total = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
        // Prefer saved percent if available (more accurate), otherwise calculate
        $percent = isset( $active_progress['percent'] ) ? floatval( $active_progress['percent'] ) : ( $total > 0 ? round( ( $current / $total ) * 100, 1 ) : 0 );
        
        $response_data['active'] = true;
        $response_data['stage'] = 'full';
        $response_data['progress'] = array(
            'current' => $current,
            'total' => $total,
            'percent' => $percent,
            'remaining' => $total - $current,
        );
        
        // Calculate ETA
        if ( isset( $active_progress['timestamp'] ) && $current > 0 ) {
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
    
    // Add progress data to heartbeat response for real-time updates
    wp_cache_delete( 'yatco_import_progress', 'transient' );
    wp_cache_delete( 'yatco_cache_warming_status', 'transient' );
    $import_progress = get_transient( 'yatco_import_progress' );
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    
    if ( $import_progress !== false && is_array( $import_progress ) ) {
        $current = isset( $import_progress['processed'] ) ? intval( $import_progress['processed'] ) : ( isset( $import_progress['last_processed'] ) ? intval( $import_progress['last_processed'] ) : 0 );
        $total = isset( $import_progress['total'] ) ? intval( $import_progress['total'] ) : 0;
        $percent = isset( $import_progress['percent'] ) ? floatval( $import_progress['percent'] ) : ( $total > 0 ? round( ( $current / $total ) * 100, 1 ) : 0 );
        
        $progress_data = array(
            'active' => true,
            'current' => $current,
            'total' => $total,
            'percent' => $percent,
            'remaining' => $total - $current,
            'status' => $cache_status !== false ? $cache_status : null,
        );
        
        // Calculate ETA
        if ( isset( $import_progress['timestamp'] ) && $current > 0 && $total > $current ) {
            $time_elapsed = time() - intval( $import_progress['timestamp'] );
            if ( $time_elapsed > 0 && $current > 0 ) {
                $rate = $current / $time_elapsed; // items per second
                $remaining = $total - $current;
                if ( $rate > 0 ) {
                    $eta_seconds = $remaining / $rate;
                    $progress_data['eta_minutes'] = round( $eta_seconds / 60, 1 );
                }
            }
        }
        
        $response['yatco_import_progress'] = $progress_data;
    }
    
    return $response;
}

// Increase heartbeat frequency for real-time updates
add_filter( 'heartbeat_settings', 'yatco_heartbeat_settings' );
function yatco_heartbeat_settings( $settings ) {
    // Increase heartbeat frequency to 2 seconds for real-time updates (when on admin pages)
    if ( is_admin() ) {
        $settings['interval'] = 2;
    }
    return $settings;
}

// AJAX handler to stop import
add_action( 'wp_ajax_yatco_stop_import', 'yatco_ajax_stop_import' );
function yatco_ajax_stop_import() {
    check_ajax_referer( 'yatco_stop_import', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    yatco_log( 'Import: Stop requested via AJAX', 'info' );
    
    // Set stop flag using WordPress option (more reliable than transient for direct runs)
    // This persists in the database and is immediately available
    update_option( 'yatco_import_stop_flag', time(), false );
    
    // Also set as transient for backwards compatibility
    set_transient( 'yatco_cache_warming_stop', time(), 900 );
    
    // Cancel any scheduled cron jobs
    $scheduled_full = wp_next_scheduled( 'yatco_full_import_hook' );
    $scheduled_warm = wp_next_scheduled( 'yatco_warm_cache_hook' );
    
    if ( $scheduled_full ) {
        wp_unschedule_event( $scheduled_full, 'yatco_full_import_hook' );
        yatco_log( 'Import: Cancelled scheduled Full Import event', 'info' );
    }
    if ( $scheduled_warm ) {
        wp_unschedule_event( $scheduled_warm, 'yatco_warm_cache_hook' );
        yatco_log( 'Import: Cancelled scheduled warm cache event', 'info' );
    }
    
    wp_clear_scheduled_hook( 'yatco_stage1_import_hook' );
    wp_clear_scheduled_hook( 'yatco_stage2_import_hook' );
    wp_clear_scheduled_hook( 'yatco_stage3_import_hook' );
    wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
    
    // Clear progress and status
    delete_transient( 'yatco_stage1_progress' );
    delete_transient( 'yatco_stage2_progress' );
    delete_transient( 'yatco_stage3_progress' );
    delete_transient( 'yatco_cache_warming_progress' );
    delete_transient( 'yatco_cache_warming_status' );
    
    set_transient( 'yatco_cache_warming_status', 'Import stopped by user.', 60 );
    yatco_log( 'Import: Stop signal sent and progress cleared', 'info' );
    
    wp_send_json_success( array( 'message' => 'Stop signal sent. Import will stop at next checkpoint.' ) );
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