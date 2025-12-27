<?php
/**
 * Progress Storage Functions
 * 
 * Uses wp_options instead of transients for more reliable, persistent storage
 * as recommended for WordPress import systems (WooCommerce, WP All Import style)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Update import status/progress (using wp_options for reliability)
 * 
 * @param array $status_data Status data array
 * @param string $type Type of import: 'full' or 'daily_sync'
 */
function yatco_update_import_status( $status_data, $type = 'full' ) {
    // Ensure we have an updated timestamp
    $status_data['updated'] = time();
    
    // Add status field if not present
    if ( ! isset( $status_data['status'] ) ) {
        $status_data['status'] = 'running';
    }
    
    // Store in wp_options with autoload=false to bypass object cache
    $option_name = $type === 'daily_sync' ? 'yatco_daily_sync_status' : 'yatco_import_status';
    update_option( $option_name, $status_data, false );
    
    // Force cache flush to ensure immediate availability
    wp_cache_delete( $option_name, 'options' );
    wp_cache_flush();
}

/**
 * Get import status/progress (using wp_options)
 * 
 * @param string $type Type of import: 'full' or 'daily_sync'
 * @return array|false Status data or false if not found
 */
function yatco_get_import_status( $type = 'full' ) {
    // Bypass object cache to get fresh data
    $option_name = $type === 'daily_sync' ? 'yatco_daily_sync_status' : 'yatco_import_status';
    wp_cache_delete( $option_name, 'options' );
    
    $status = get_option( $option_name, false );
    
    // If status exists but hasn't been updated in 5 minutes, consider it stalled
    // Check both 'updated_at' and 'updated' for backward compatibility
    if ( $status !== false && is_array( $status ) ) {
        $updated_time = isset( $status['updated_at'] ) ? intval( $status['updated_at'] ) : ( isset( $status['updated'] ) ? intval( $status['updated'] ) : false );
        if ( $updated_time !== false ) {
            $age = time() - $updated_time;
            if ( $age > 300 && isset( $status['status'] ) && $status['status'] === 'running' ) {
                // Mark as stalled (but don't delete - useful for debugging)
                $status['status'] = 'stalled';
                $status['stalled_since'] = $updated_time;
            }
        }
    }
    
    return $status;
}

/**
 * Clear import status/progress
 * 
 * @param string $type Type of import: 'full' or 'daily_sync'
 */
function yatco_clear_import_status( $type = 'full' ) {
    $option_name = $type === 'daily_sync' ? 'yatco_daily_sync_status' : 'yatco_import_status';
    delete_option( $option_name );
    wp_cache_delete( $option_name, 'options' );
    wp_cache_flush();
}

/**
 * Update import status message (stored in wp_options for consistency)
 * 
 * @param string $message Status message
 * @param int $expires Expiration time in seconds (default 600 = 10 minutes) - for reference only, wp_options don't expire
 */
function yatco_update_import_status_message( $message, $expires = 600 ) {
    // Store status message in wp_options for consistency with progress data
    $status_data = array(
        'message' => $message,
        'updated_at' => time(),
    );
    update_option( 'yatco_import_status_message', $status_data, false );
    wp_cache_delete( 'yatco_import_status_message', 'options' );
}

/**
 * Get import status message (from wp_options)
 * 
 * @return string|false Status message or false if not found
 */
function yatco_get_import_status_message() {
    wp_cache_delete( 'yatco_import_status_message', 'options' );
    $status_data = get_option( 'yatco_import_status_message', false );
    if ( $status_data !== false && is_array( $status_data ) && isset( $status_data['message'] ) ) {
        return $status_data['message'];
    }
    return false;
}

/**
 * Clear import status message
 */
function yatco_clear_import_status_message() {
    delete_option( 'yatco_import_status_message' );
    wp_cache_delete( 'yatco_import_status_message', 'options' );
}

