<?php
/**
 * Cache Management
 * 
 * Handles cache warming, refreshing, and scheduling.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Warm cache function - pre-loads all vessels into cache.
 * This runs in the background via WP-Cron.
 */
function yatco_warm_cache_function() {
    update_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
    
    $token = yatco_get_token();
    if ( empty( $token ) ) {
        update_transient( 'yatco_cache_warming_status', 'Error: API token not configured', 60 );
        return;
    }

    // Use default shortcode attributes
    $atts = array(
        'max'           => '999999',
        'price_min'     => '',
        'price_max'     => '',
        'year_min'      => '',
        'year_max'      => '',
        'loa_min'       => '',
        'loa_max'       => '',
        'columns'       => '3',
        'show_price'    => 'yes',
        'show_year'     => 'yes',
        'show_loa'      => 'yes',
        'cache'         => 'no', // Don't check cache when warming
        'show_filters'  => 'yes',
        'currency'      => 'USD',
        'length_unit'   => 'FT',
    );

    update_transient( 'yatco_cache_warming_status', 'Fetching vessel IDs...', 600 );
    
    // Increase limits for cache warming
    @ini_set( 'max_execution_time', 0 ); // Unlimited
    @ini_set( 'memory_limit', '512M' ); // Increase memory
    @set_time_limit( 0 ); // Remove time limit
    
    // Fetch all vessel IDs
    $ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $ids ) ) {
        update_transient( 'yatco_cache_warming_status', 'Error: ' . $ids->get_error_message(), 60 );
        return;
    }

    $vessel_count = count( $ids );
    update_transient( 'yatco_cache_warming_status', "Processing {$vessel_count} vessels...", 600 );

    // Check for partial progress
    $cache_key_progress = 'yatco_cache_warming_progress';
    $progress = get_transient( $cache_key_progress );
    $start_from = 0;
    $cached_partial = array();
    
    if ( $progress !== false && is_array( $progress ) ) {
        $start_from = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        $cached_partial = isset( $progress['vessels'] ) && is_array( $progress['vessels'] ) ? $progress['vessels'] : array();
        $ids = array_slice( $ids, $start_from );
        $vessels = $cached_partial;
    } else {
        $vessels = array();
    }
    
    $batch_size = 50; // Process 50 at a time
    $processed = 0;
    $errors = 0;
    $batch_num = 0;

    foreach ( $ids as $index => $id ) {
        $processed++;
        $actual_index = $start_from + $index;
        
        // Save progress every batch
        if ( $processed % $batch_size === 0 ) {
            $batch_num++;
            
            // Save progress
            $progress_data = array(
                'last_processed' => $actual_index,
                'total'         => $vessel_count,
                'processed'     => count( $vessels ),
                'vessels'       => $vessels,
                'timestamp'     => time(),
            );
            set_transient( $cache_key_progress, $progress_data, 3600 );
            
            // Update status
            $percent = round( ( $actual_index / $vessel_count ) * 100, 1 );
            update_transient( 'yatco_cache_warming_status', "Processing vessel {$actual_index} of {$vessel_count} ({$percent}%)...", 600 );
            
            // Reset execution time and flush
            @set_time_limit( 0 );
            if ( function_exists( 'fastcgi_finish_request' ) ) {
                @fastcgi_finish_request();
            }
            
            // Small delay to reduce server load
            usleep( 100000 ); // 0.1 second
        }
        
        // Reset execution time periodically
        if ( $processed % 10 === 0 ) {
            @set_time_limit( 0 );
        }

        $full = yatco_fetch_fullspecs( $token, $id );
        if ( is_wp_error( $full ) ) {
            $errors++;
            continue;
        }

        $brief = yatco_build_brief_from_fullspecs( $id, $full );

        // Get full specs for display
        $result = isset( $full['Result'] ) ? $full['Result'] : array();
        $basic  = isset( $full['BasicInfo'] ) ? $full['BasicInfo'] : array();
        
        // Get builder, category, type, condition
        $builder = isset( $basic['Builder'] ) ? $basic['Builder'] : ( isset( $result['BuilderName'] ) ? $result['BuilderName'] : '' );
        $category = isset( $basic['MainCategory'] ) ? $basic['MainCategory'] : ( isset( $result['MainCategoryText'] ) ? $result['MainCategoryText'] : '' );
        $type = isset( $basic['VesselTypeText'] ) ? $basic['VesselTypeText'] : ( isset( $result['VesselTypeText'] ) ? $result['VesselTypeText'] : '' );
        $condition = isset( $result['VesselCondition'] ) ? $result['VesselCondition'] : '';
        $state_rooms = isset( $basic['StateRooms'] ) ? intval( $basic['StateRooms'] ) : ( isset( $result['StateRooms'] ) ? intval( $result['StateRooms'] ) : 0 );
        $location = isset( $basic['LocationCustom'] ) ? $basic['LocationCustom'] : '';
        
        // Get LOA in feet and meters
        $loa_feet = isset( $result['LOAFeet'] ) && $result['LOAFeet'] > 0 ? floatval( $result['LOAFeet'] ) : null;
        $loa_meters = isset( $result['LOAMeters'] ) && $result['LOAMeters'] > 0 ? floatval( $result['LOAMeters'] ) : null;
        if ( ! $loa_meters && $loa_feet ) {
            $loa_meters = $loa_feet * 0.3048;
        }
        
        // Get price in USD and EUR
        $price_usd = isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ? floatval( $basic['AskingPriceUSD'] ) : null;
        if ( ! $price_usd && isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
            $price_usd = floatval( $result['AskingPriceCompare'] );
        }
        
        $price_eur = isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 && isset( $basic['Currency'] ) && $basic['Currency'] === 'EUR' ? floatval( $basic['AskingPrice'] ) : null;
        
        $vessel_data = array(
            'id'          => $id,
            'name'        => $brief['Name'],
            'price'       => $brief['Price'],
            'price_usd'   => $price_usd,
            'price_eur'   => $price_eur,
            'year'        => $brief['Year'],
            'loa'         => $brief['LOA'],
            'loa_feet'    => $loa_feet,
            'loa_meters'  => $loa_meters,
            'builder'     => $builder,
            'category'    => $category,
            'type'        => $type,
            'condition'   => $condition,
            'state_rooms' => $state_rooms,
            'location'    => $location,
            'image'       => isset( $result['MainPhotoUrl'] ) ? $result['MainPhotoUrl'] : ( isset( $basic['MainPhotoURL'] ) ? $basic['MainPhotoURL'] : '' ),
            'link'        => get_post_type_archive_link( 'yacht' ) . '?vessel_id=' . $id,
        );

        $vessels[] = $vessel_data;
    }

    // Collect unique values for filter dropdowns
    $builders = array();
    $categories = array();
    $types = array();
    $conditions = array();
    
    foreach ( $vessels as $vessel ) {
        if ( ! empty( $vessel['builder'] ) && ! in_array( $vessel['builder'], $builders ) ) {
            $builders[] = $vessel['builder'];
        }
        if ( ! empty( $vessel['category'] ) && ! in_array( $vessel['category'], $categories ) ) {
            $categories[] = $vessel['category'];
        }
        if ( ! empty( $vessel['type'] ) && ! in_array( $vessel['type'], $types ) ) {
            $types[] = $vessel['type'];
        }
        if ( ! empty( $vessel['condition'] ) && ! in_array( $vessel['condition'], $conditions ) ) {
            $conditions[] = $vessel['condition'];
        }
    }
    sort( $builders );
    sort( $categories );
    sort( $types );
    sort( $conditions );

    // Get options for cache duration
    $options = get_option( 'yatco_api_settings' );
    $cache_duration = isset( $options['yatco_cache_duration'] ) ? intval( $options['yatco_cache_duration'] ) : 30;
    
    // Cache vessel data for fast retrieval
    set_transient( 'yatco_vessels_data', $vessels, $cache_duration * MINUTE_IN_SECONDS );
    set_transient( 'yatco_vessels_builders', $builders, $cache_duration * MINUTE_IN_SECONDS );
    set_transient( 'yatco_vessels_categories', $categories, $cache_duration * MINUTE_IN_SECONDS );
    set_transient( 'yatco_vessels_types', $types, $cache_duration * MINUTE_IN_SECONDS );
    set_transient( 'yatco_vessels_conditions', $conditions, $cache_duration * MINUTE_IN_SECONDS );
    
    // Clear progress after successful completion
    delete_transient( $cache_key_progress );
    
    $total_processed = count( $vessels );
    $success_msg = "Cache warmed successfully! Processed {$total_processed} vessels";
    if ( $errors > 0 ) {
        $success_msg .= " ({$errors} errors)";
    }
    update_transient( 'yatco_cache_warming_status', $success_msg, 300 );
}

/**
 * Schedule periodic cache refresh if enabled.
 */
function yatco_maybe_schedule_cache_refresh() {
    $options = get_option( 'yatco_api_settings' );
    $auto_refresh = isset( $options['yatco_auto_refresh_cache'] ) && $options['yatco_auto_refresh_cache'] === 'yes';
    
    if ( $auto_refresh ) {
        if ( ! wp_next_scheduled( 'yatco_auto_refresh_cache_hook' ) ) {
            wp_schedule_event( time(), 'yatco_six_hours', 'yatco_auto_refresh_cache_hook' );
        }
    } else {
        $timestamp = wp_next_scheduled( 'yatco_auto_refresh_cache_hook' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'yatco_auto_refresh_cache_hook' );
        }
    }
}

/**
 * Register custom cron schedule for 6 hours.
 */
function yatco_add_six_hour_schedule( $schedules ) {
    $schedules['yatco_six_hours'] = array(
        'interval' => 21600, // 6 hours in seconds
        'display'  => 'Every 6 Hours',
    );
    return $schedules;
}

// Register custom cron schedule for 6 hours
add_filter( 'cron_schedules', 'yatco_add_six_hour_schedule' );

// Hook for auto-refresh
add_action( 'yatco_auto_refresh_cache_hook', 'yatco_warm_cache_function' );

