<?php
/**
 * Staged Import System
 * 
 * Implements a multi-stage import process to reduce server load:
 * - Stage 1: Fetch all active vessel IDs and names only (lightweight)
 * - Stage 2: Fetch images for vessels from Stage 1
 * - Stage 3: Fetch full data (descriptions, specs, etc.) for vessels
 * - Daily Sync: Check IDs for removed vessels and price changes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stage 1: Fetch all active vessel IDs and names only (lightweight).
 * Creates minimal CPT posts with just ID and name.
 */
function yatco_stage1_import_ids_and_names( $token ) {
    // Check stop flag
    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    if ( $stop_flag !== false ) {
        delete_transient( 'yatco_cache_warming_stop' );
        delete_transient( 'yatco_stage1_progress' );
        set_transient( 'yatco_cache_warming_status', 'Stage 1 cancelled.', 60 );
        return;
    }
    
    set_transient( 'yatco_cache_warming_status', 'Stage 1: Fetching vessel IDs and names...', 600 );
    
    // Fetch all active vessel IDs
    $vessel_ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $vessel_ids ) ) {
        set_transient( 'yatco_cache_warming_status', 'Stage 1 Error: ' . $vessel_ids->get_error_message(), 60 );
        return;
    }
    
    $total = count( $vessel_ids );
    set_transient( 'yatco_cache_warming_status', "Stage 1: Processing {$total} vessel IDs...", 600 );
    
    // Get progress
    $progress = get_transient( 'yatco_stage1_progress' );
    $start_from = 0;
    $processed_ids = array();
    
    if ( $progress !== false && is_array( $progress ) ) {
        $start_from = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        $processed_ids = isset( $progress['processed_ids'] ) ? $progress['processed_ids'] : array();
        $vessel_ids = array_slice( $vessel_ids, $start_from );
    }
    
    $processed = 0;
    $batch_size = 25; // Reduced to 25 to prevent server overload
    $delay_seconds = 3; // Increased to 3 seconds between batches for safety
    $delay_between_items = 0.1; // 100ms delay between individual items
    
    foreach ( array_chunk( $vessel_ids, $batch_size ) as $batch ) {
        // Check stop flag
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        if ( $stop_flag !== false ) {
            delete_transient( 'yatco_cache_warming_stop' );
            set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                delete_transient( 'yatco_cache_warming_stop' );
                set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
                return;
            }
            
            // Small delay between items to prevent server overload
            if ( $delay_between_items > 0 ) {
                usleep( $delay_between_items * 1000000 ); // Convert to microseconds
            }
            
            // Fetch lightweight data (just Result section for name)
            $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll';
            $response = wp_remote_get(
                $endpoint,
                array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . $token,
                        'Accept'        => 'application/json',
                    ),
                    'timeout' => 15,
                )
            );
            
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                continue; // Skip on error
            }
            
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $data ) || ! isset( $data['Result'] ) ) {
                continue; // Skip if no data
            }
            
            $result = $data['Result'];
            $name = isset( $result['VesselName'] ) ? $result['VesselName'] : 'Vessel ' . $vessel_id;
            $mlsid = isset( $result['MLSID'] ) ? $result['MLSID'] : $vessel_id;
            
            // Find or create post
            $post_id = yatco_find_or_create_vessel_post( $vessel_id, $mlsid, $name );
            
            if ( $post_id ) {
                // Store minimal data
                update_post_meta( $post_id, 'yacht_vessel_id', $vessel_id );
                update_post_meta( $post_id, 'yacht_mlsid', $mlsid );
                update_post_meta( $post_id, 'yacht_import_stage', 1 );
                update_post_meta( $post_id, 'yacht_last_updated', time() );
                
                $processed_ids[] = $vessel_id;
                $processed++;
            }
        }
        
        // Save progress with percentage
        $current_total = $start_from + $processed;
        $percent = $total > 0 ? round( ( $current_total / $total ) * 100, 1 ) : 0;
        $progress_data = array(
            'last_processed' => $current_total,
            'total'         => $total,
            'processed_ids' => array_slice( $processed_ids, -1000 ), // Keep last 1000
            'timestamp'     => time(),
            'percent'       => $percent,
            'stage'         => 1,
        );
        set_transient( 'yatco_stage1_progress', $progress_data, 3600 );
        set_transient( 'yatco_cache_warming_status', "Stage 1: Processed {$current_total} of {$total} vessels ({$percent}%)...", 600 );
        
        // Delay between batches
        if ( $processed < $total ) {
            sleep( $delay_seconds );
        }
    }
    
    // Store all vessel IDs for later stages
    update_option( 'yatco_stage1_vessel_ids', $processed_ids, false );
    
    // Clear progress
    delete_transient( 'yatco_stage1_progress' );
    set_transient( 'yatco_cache_warming_status', "Stage 1 Complete: Processed {$processed} vessels. Ready for Stage 2.", 300 );
}

/**
 * Stage 2: Fetch images for vessels from Stage 1.
 */
function yatco_stage2_import_images( $token ) {
    // Check stop flag
    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    if ( $stop_flag !== false ) {
        delete_transient( 'yatco_cache_warming_stop' );
        delete_transient( 'yatco_stage2_progress' );
        set_transient( 'yatco_cache_warming_status', 'Stage 2 cancelled.', 60 );
        return;
    }
    
    set_transient( 'yatco_cache_warming_status', 'Stage 2: Fetching images...', 600 );
    
    // Get vessel IDs from Stage 1
    $vessel_ids = get_option( 'yatco_stage1_vessel_ids', array() );
    
    if ( empty( $vessel_ids ) ) {
        set_transient( 'yatco_cache_warming_status', 'Stage 2 Error: No vessel IDs from Stage 1. Run Stage 1 first.', 60 );
        return;
    }
    
    $total = count( $vessel_ids );
    set_transient( 'yatco_cache_warming_status', "Stage 2: Processing images for {$total} vessels...", 600 );
    
    // Get progress
    $progress = get_transient( 'yatco_stage2_progress' );
    $start_from = 0;
    
    if ( $progress !== false && is_array( $progress ) ) {
        $start_from = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        $vessel_ids = array_slice( $vessel_ids, $start_from );
    }
    
    $processed = 0;
    $batch_size = 15; // Smaller batches for images to prevent overload
    $delay_seconds = 4; // 4 second delay between batches
    $delay_between_items = 0.2; // 200ms delay between individual items
    
    foreach ( array_chunk( $vessel_ids, $batch_size ) as $batch ) {
        // Check stop flag
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        if ( $stop_flag !== false ) {
            delete_transient( 'yatco_cache_warming_stop' );
            set_transient( 'yatco_cache_warming_status', 'Stage 2 stopped by user.', 60 );
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                delete_transient( 'yatco_cache_warming_stop' );
                set_transient( 'yatco_cache_warming_status', 'Stage 2 stopped by user.', 60 );
                return;
            }
            
            // Small delay between items to prevent server overload
            if ( $delay_between_items > 0 ) {
                usleep( $delay_between_items * 1000000 ); // Convert to microseconds
            }
            
            // Find post
            $post_id = yatco_find_vessel_post_by_id( $vessel_id );
            if ( ! $post_id ) {
                continue; // Skip if post not found
            }
            
            // Fetch FullSpecsAll to get images
            $full = yatco_fetch_fullspecs( $token, $vessel_id );
            if ( is_wp_error( $full ) ) {
                continue; // Skip on error
            }
            
            // Extract images
            $gallery_images = array();
            if ( isset( $full['PhotoGallery'] ) && is_array( $full['PhotoGallery'] ) ) {
                foreach ( $full['PhotoGallery'] as $photo ) {
                    if ( isset( $photo['largeImageURL'] ) ) {
                        $gallery_images[] = array(
                            'url' => $photo['largeImageURL'],
                            'mediumImageURL' => isset( $photo['mediumImageURL'] ) ? $photo['mediumImageURL'] : $photo['largeImageURL'],
                            'smallImageURL' => isset( $photo['smallImageURL'] ) ? $photo['smallImageURL'] : $photo['largeImageURL'],
                            'caption' => isset( $photo['Caption'] ) ? $photo['Caption'] : '',
                        );
                    }
                }
            }
            
            // Store images
            if ( ! empty( $gallery_images ) ) {
                update_post_meta( $post_id, 'yacht_image_gallery_urls', $gallery_images );
                
                // Set main image
                if ( isset( $gallery_images[0]['url'] ) ) {
                    update_post_meta( $post_id, 'yacht_image_url', $gallery_images[0]['url'] );
                }
            }
            
            update_post_meta( $post_id, 'yacht_import_stage', 2 );
            update_post_meta( $post_id, 'yacht_last_updated', time() );
            
            $processed++;
        }
        
        // Save progress with percentage
        $current_total = $start_from + $processed;
        $percent = $total > 0 ? round( ( $current_total / $total ) * 100, 1 ) : 0;
        $progress_data = array(
            'last_processed' => $current_total,
            'total'         => $total,
            'timestamp'     => time(),
            'percent'       => $percent,
            'stage'         => 2,
        );
        set_transient( 'yatco_stage2_progress', $progress_data, 3600 );
        set_transient( 'yatco_cache_warming_status', "Stage 2: Processed images for {$current_total} of {$total} vessels ({$percent}%)...", 600 );
        
        // Delay between batches
        if ( $processed < $total ) {
            sleep( $delay_seconds );
        }
    }
    
    // Clear progress
    delete_transient( 'yatco_stage2_progress' );
    set_transient( 'yatco_cache_warming_status', "Stage 2 Complete: Processed images for {$processed} vessels. Ready for Stage 3.", 300 );
}

/**
 * Stage 3: Fetch full data (descriptions, specs, etc.) for vessels.
 */
function yatco_stage3_import_full_data( $token ) {
    // Check stop flag
    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    if ( $stop_flag !== false ) {
        delete_transient( 'yatco_cache_warming_stop' );
        delete_transient( 'yatco_stage3_progress' );
        set_transient( 'yatco_cache_warming_status', 'Stage 3 cancelled.', 60 );
        return;
    }
    
    set_transient( 'yatco_cache_warming_status', 'Stage 3: Fetching full vessel data...', 600 );
    
    // Get vessel IDs from Stage 1
    $vessel_ids = get_option( 'yatco_stage1_vessel_ids', array() );
    
    if ( empty( $vessel_ids ) ) {
        set_transient( 'yatco_cache_warming_status', 'Stage 3 Error: No vessel IDs from Stage 1. Run Stage 1 first.', 60 );
        return;
    }
    
    $total = count( $vessel_ids );
    set_transient( 'yatco_cache_warming_status', "Stage 3: Processing full data for {$total} vessels...", 600 );
    
    // Get progress
    $progress = get_transient( 'yatco_stage3_progress' );
    $start_from = 0;
    
    if ( $progress !== false && is_array( $progress ) ) {
        $start_from = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        $vessel_ids = array_slice( $vessel_ids, $start_from );
    }
    
    $processed = 0;
    $batch_size = 5; // Very small batches for full data to prevent overload
    $delay_seconds = 6; // 6 second delay between batches
    $delay_between_items = 0.5; // 500ms delay between individual items
    
    foreach ( array_chunk( $vessel_ids, $batch_size ) as $batch ) {
        // Check stop flag
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        if ( $stop_flag !== false ) {
            delete_transient( 'yatco_cache_warming_stop' );
            set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                delete_transient( 'yatco_cache_warming_stop' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                return;
            }
            
            // Small delay between items to prevent server overload
            if ( $delay_between_items > 0 ) {
                usleep( $delay_between_items * 1000000 ); // Convert to microseconds
            }
            
            // Use existing import function for full data
            $import_result = yatco_import_single_vessel( $token, $vessel_id );
            
            if ( ! is_wp_error( $import_result ) ) {
                $post_id = $import_result;
                update_post_meta( $post_id, 'yacht_import_stage', 3 );
                $processed++;
            }
        }
        
        // Save progress with percentage
        $current_total = $start_from + $processed;
        $percent = $total > 0 ? round( ( $current_total / $total ) * 100, 1 ) : 0;
        $progress_data = array(
            'last_processed' => $current_total,
            'total'         => $total,
            'timestamp'     => time(),
            'percent'       => $percent,
            'stage'         => 3,
        );
        set_transient( 'yatco_stage3_progress', $progress_data, 3600 );
        set_transient( 'yatco_cache_warming_status', "Stage 3: Processed full data for {$current_total} of {$total} vessels ({$percent}%)...", 600 );
        
        // Delay between batches
        if ( $processed < $total ) {
            sleep( $delay_seconds );
        }
    }
    
    // Clear progress
    delete_transient( 'yatco_stage3_progress' );
    set_transient( 'yatco_cache_warming_status', "Stage 3 Complete: Processed full data for {$processed} vessels.", 300 );
}

/**
 * Daily Sync: Check IDs for removed vessels and price changes.
 */
function yatco_daily_sync_check( $token ) {
    set_transient( 'yatco_cache_warming_status', 'Daily Sync: Checking for changes...', 600 );
    
    // Get current active vessel IDs from API
    $current_ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $current_ids ) ) {
        set_transient( 'yatco_cache_warming_status', 'Daily Sync Error: ' . $current_ids->get_error_message(), 60 );
        return;
    }
    
    // Get stored vessel IDs
    $stored_ids = get_option( 'yatco_stage1_vessel_ids', array() );
    
    // Find removed vessels
    $removed_ids = array_diff( $stored_ids, $current_ids );
    
    // Find new vessels
    $new_ids = array_diff( $current_ids, $stored_ids );
    
    // Update stored IDs
    update_option( 'yatco_stage1_vessel_ids', $current_ids, false );
    
    // Mark removed vessels as draft
    foreach ( $removed_ids as $vessel_id ) {
        $post_id = yatco_find_vessel_post_by_id( $vessel_id );
        if ( $post_id ) {
            wp_update_post( array(
                'ID' => $post_id,
                'post_status' => 'draft',
            ) );
            update_post_meta( $post_id, 'yacht_removed_from_api', true );
            update_post_meta( $post_id, 'yacht_removed_date', time() );
        }
    }
    
    // Check for price changes on existing vessels
    $price_changes = 0;
    $batch_size = 50;
    $delay_seconds = 1;
    
    foreach ( array_chunk( array_intersect( $current_ids, $stored_ids ), $batch_size ) as $batch ) {
        foreach ( $batch as $vessel_id ) {
            $post_id = yatco_find_vessel_post_by_id( $vessel_id );
            if ( ! $post_id ) {
                continue;
            }
            
            // Fetch lightweight price data
            $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll';
            $response = wp_remote_get(
                $endpoint,
                array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . $token,
                        'Accept'        => 'application/json',
                    ),
                    'timeout' => 15,
                )
            );
            
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                continue;
            }
            
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $data ) || ! isset( $data['Result'] ) ) {
                continue;
            }
            
            $result = $data['Result'];
            $current_price = isset( $result['AskingPriceCompare'] ) ? floatval( $result['AskingPriceCompare'] ) : 0;
            $stored_price = floatval( get_post_meta( $post_id, 'yacht_price_usd', true ) );
            
            if ( $current_price > 0 && $stored_price > 0 && abs( $current_price - $stored_price ) > 0.01 ) {
                // Price changed - update it
                update_post_meta( $post_id, 'yacht_price_usd', $current_price );
                update_post_meta( $post_id, 'yacht_price', $current_price );
                if ( isset( $result['AskingPriceFormatted'] ) ) {
                    update_post_meta( $post_id, 'yacht_price_formatted', $result['AskingPriceFormatted'] );
                }
                update_post_meta( $post_id, 'yacht_price_changed', true );
                update_post_meta( $post_id, 'yacht_price_changed_date', time() );
                $price_changes++;
            }
        }
        
        sleep( $delay_seconds );
    }
    
    $removed_count = count( $removed_ids );
    $new_count = count( $new_ids );
    
    $status_msg = "Daily Sync Complete: {$removed_count} removed, {$new_count} new, {$price_changes} price changes.";
    set_transient( 'yatco_cache_warming_status', $status_msg, 300 );
    
    // Store sync results
    update_option( 'yatco_daily_sync_last_run', time() );
    update_option( 'yatco_daily_sync_results', array(
        'removed' => $removed_count,
        'new' => $new_count,
        'price_changes' => $price_changes,
        'timestamp' => time(),
    ), false );
}

/**
 * Helper: Find or create vessel post by VesselID or MLSID.
 */
function yatco_find_or_create_vessel_post( $vessel_id, $mlsid, $name ) {
    // Try to find by MLSID first
    $posts = get_posts( array(
        'post_type' => 'yacht',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'yacht_mlsid',
                'value' => $mlsid,
                'compare' => '=',
            ),
            array(
                'key' => 'yacht_vessel_id',
                'value' => $vessel_id,
                'compare' => '=',
            ),
        ),
        'posts_per_page' => 1,
        'post_status' => 'any',
    ) );
    
    if ( ! empty( $posts ) ) {
        return $posts[0]->ID;
    }
    
    // Create new post
    $post_id = wp_insert_post( array(
        'post_title' => $name,
        'post_type' => 'yacht',
        'post_status' => 'publish',
    ) );
    
    return $post_id;
}

/**
 * Helper: Find vessel post by VesselID.
 */
function yatco_find_vessel_post_by_id( $vessel_id ) {
    $posts = get_posts( array(
        'post_type' => 'yacht',
        'meta_query' => array(
            array(
                'key' => 'yacht_vessel_id',
                'value' => $vessel_id,
                'compare' => '=',
            ),
        ),
        'posts_per_page' => 1,
        'post_status' => 'any',
    ) );
    
    if ( ! empty( $posts ) ) {
        return $posts[0]->ID;
    }
    
    return false;
}

