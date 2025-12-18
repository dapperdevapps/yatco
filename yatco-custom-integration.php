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

// Register staged import hooks
add_action( 'yatco_stage1_import_hook', function() {
    yatco_log( 'Stage 1: Hook triggered via WP-Cron', 'info' );
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_log( 'Stage 1: Token found, calling import function', 'info' );
        yatco_stage1_import_ids_and_names( $token );
    } else {
        yatco_log( 'Stage 1: Hook triggered but no token found', 'error' );
    }
} );
add_action( 'yatco_stage2_import_hook', function() {
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_stage2_import_images( $token );
    }
} );
add_action( 'yatco_stage3_import_hook', function() {
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_stage3_import_full_data( $token );
    }
} );

// AJAX handler to run Stage 1 directly (for when WP-Cron isn't working)
add_action( 'wp_ajax_yatco_run_stage1_direct', 'yatco_ajax_run_stage1_direct' );
function yatco_ajax_run_stage1_direct() {
    check_ajax_referer( 'yatco_run_stage1', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        yatco_log( 'Stage 1 Direct: Unauthorized access attempt', 'error' );
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    yatco_log( 'Stage 1 Direct: AJAX handler called', 'info' );
    
    // Increase execution time
    @set_time_limit( 300 );
    @ini_set( 'max_execution_time', 300 );
    yatco_log( 'Stage 1 Direct: Execution time limit set to 300 seconds', 'info' );
    
    $token = yatco_get_token();
    if ( ! empty( $token ) ) {
        yatco_log( 'Stage 1 Direct: Token found, starting import', 'info' );
        // Run Stage 1
        yatco_stage1_import_ids_and_names( $token );
        yatco_log( 'Stage 1 Direct: Import function completed', 'info' );
        wp_send_json_success( array( 'message' => 'Stage 1 completed' ) );
    } else {
        yatco_log( 'Stage 1 Direct: No API token found', 'error' );
        wp_send_json_error( array( 'message' => 'No API token' ) );
    }
}

// AJAX handler for import status
add_action( 'wp_ajax_yatco_get_import_status', 'yatco_ajax_get_import_status' );
function yatco_ajax_get_import_status() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    // Get all progress data
    $stage1_progress = get_transient( 'yatco_stage1_progress' );
    $stage2_progress = get_transient( 'yatco_stage2_progress' );
    $stage3_progress = get_transient( 'yatco_stage3_progress' );
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    
    // Determine which stage is active
    $active_stage = 0;
    $active_progress = null;
    if ( $stage3_progress !== false && is_array( $stage3_progress ) ) {
        $active_stage = 3;
        $active_progress = $stage3_progress;
    } elseif ( $stage2_progress !== false && is_array( $stage2_progress ) ) {
        $active_stage = 2;
        $active_progress = $stage2_progress;
    } elseif ( $stage1_progress !== false && is_array( $stage1_progress ) ) {
        $active_stage = 1;
        $active_progress = $stage1_progress;
    } elseif ( $cache_progress !== false && is_array( $cache_progress ) ) {
        $active_stage = 'full';
        $active_progress = $cache_progress;
    }
    
    ob_start();
    
    if ( $active_stage > 0 || $active_stage === 'full' ) {
        echo '<div id="yatco-import-status" style="background: #fff; border: 2px solid #2271b1; border-radius: 4px; padding: 20px; margin: 20px 0;">';
        $current = isset( $active_progress['last_processed'] ) ? intval( $active_progress['last_processed'] ) : 0;
        $total = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
        $percent = isset( $active_progress['percent'] ) ? floatval( $active_progress['percent'] ) : ( $total > 0 ? round( ( $current / $total ) * 100, 1 ) : 0 );
        $stage_name = $active_stage === 'full' ? 'Full Import' : "Stage {$active_stage}";
        
        echo '<h3 style="margin-top: 0; color: #2271b1;">📊 ' . esc_html( $stage_name ) . ' Progress</h3>';
        
        if ( $cache_status !== false ) {
            echo '<p style="margin: 10px 0; font-size: 14px; color: #666;"><strong>Status:</strong> ' . esc_html( $cache_status ) . '</p>';
        }
        
        echo '<div style="background: #f0f0f0; border-radius: 10px; height: 30px; margin: 15px 0; position: relative; overflow: hidden;">';
        echo '<div id="yatco-progress-bar" style="background: linear-gradient(90deg, #2271b1 0%, #46b450 100%); height: 100%; width: ' . esc_attr( $percent ) . '%; transition: width 0.3s ease; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 12px;">';
        echo esc_html( $percent ) . '%';
        echo '</div>';
        echo '</div>';
        
        echo '<div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px; color: #666;">';
        echo '<span><strong>Processed:</strong> ' . number_format( $current ) . ' / ' . number_format( $total ) . '</span>';
        echo '<span><strong>Remaining:</strong> ' . number_format( $total - $current ) . '</span>';
        echo '</div>';
        
        // Estimated time remaining
        if ( isset( $active_progress['timestamp'] ) && $current > 0 ) {
            $time_elapsed = time() - intval( $active_progress['timestamp'] );
            if ( $time_elapsed > 0 && $current > 0 ) {
                $rate = $current / $time_elapsed; // items per second
                $remaining = $total - $current;
                if ( $rate > 0 ) {
                    $eta_seconds = $remaining / $rate;
                    $eta_minutes = round( $eta_seconds / 60, 1 );
                    echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Estimated time remaining:</strong> ' . esc_html( $eta_minutes ) . ' minutes</p>';
                }
            }
        }
        
        // Stop button
        echo '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0;">';
        echo '<button type="button" id="yatco-stop-import-btn" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; padding: 8px 16px;">🛑 Stop Import</button>';
        echo '<p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">Click to cancel the current import. The import will stop at the next checkpoint.</p>';
        echo '</div>';
    } else {
        echo '<p style="margin: 0; color: #666;">No active import. Start a staged import above to see progress.</p>';
    }
    
    echo '</div>';
    $html = ob_get_clean();
    
    wp_send_json_success( array( 'html' => $html ) );
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
    
    // Set stop flag for running processes - keep it active for 5 minutes
    set_transient( 'yatco_cache_warming_stop', true, 300 );
    
    // Cancel any scheduled cron jobs
    $scheduled_stage1 = wp_next_scheduled( 'yatco_stage1_import_hook' );
    $scheduled_stage2 = wp_next_scheduled( 'yatco_stage2_import_hook' );
    $scheduled_stage3 = wp_next_scheduled( 'yatco_stage3_import_hook' );
    $scheduled_warm = wp_next_scheduled( 'yatco_warm_cache_hook' );
    
    if ( $scheduled_stage1 ) {
        wp_unschedule_event( $scheduled_stage1, 'yatco_stage1_import_hook' );
        yatco_log( 'Import: Cancelled scheduled Stage 1 event', 'info' );
    }
    if ( $scheduled_stage2 ) {
        wp_unschedule_event( $scheduled_stage2, 'yatco_stage2_import_hook' );
        yatco_log( 'Import: Cancelled scheduled Stage 2 event', 'info' );
    }
    if ( $scheduled_stage3 ) {
        wp_unschedule_event( $scheduled_stage3, 'yatco_stage3_import_hook' );
        yatco_log( 'Import: Cancelled scheduled Stage 3 event', 'info' );
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
            // Check if URL matches old format (just number before the trailing slash)
            if ( preg_match( '#https?://www\.yatco\.com/yacht/(\d+)/?$#', $listing_url, $matches ) ) {
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