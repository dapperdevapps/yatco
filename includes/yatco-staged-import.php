<?php
/**
 * YATCO Import System
 * 
 * Implements a unified import process:
 * - Full Import: Fetches all active vessels with complete data (names, images, descriptions, specs, etc.)
 * - Daily Sync: Checks for new or removed vessels only
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logging function for import debugging.
 */
function yatco_log( $message, $level = 'info' ) {
    $log_entry = array(
        'timestamp' => current_time( 'mysql' ),
        'level' => $level,
        'message' => $message,
    );
    
    // Get existing logs
    $logs = get_option( 'yatco_import_logs', array() );
    
    // Add new log entry
    $logs[] = $log_entry;
    
    // Keep only last 100 log entries to prevent database bloat
    if ( count( $logs ) > 100 ) {
        $logs = array_slice( $logs, -100 );
    }
    
    // Save logs
    update_option( 'yatco_import_logs', $logs, false );
    
    // Also log to PHP error log if WP_DEBUG is enabled
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[YATCO Import] [' . strtoupper( $level ) . '] ' . $message );
    }
}

/**
 * Stage 1: Fetch all active vessel IDs and names only (lightweight).
 * Creates minimal CPT posts with just ID and name.
 */
function yatco_stage1_import_ids_and_names( $token ) {
    yatco_log( 'Stage 1: Function started', 'info' );
    
    // Check stop flag
    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    if ( $stop_flag !== false ) {
        yatco_log( 'Stage 1: Stop flag detected, cancelling', 'warning' );
        delete_transient( 'yatco_cache_warming_stop' );
        delete_transient( 'yatco_stage1_progress' );
        set_transient( 'yatco_cache_warming_status', 'Stage 1 cancelled.', 60 );
        return;
    }
    
    yatco_log( 'Stage 1: Setting status to "Fetching vessel IDs..."', 'info' );
    set_transient( 'yatco_cache_warming_status', 'Stage 1: Fetching vessel IDs and names...', 600 );
    
    // Fetch all active vessel IDs
    yatco_log( 'Stage 1: Calling yatco_get_active_vessel_ids()', 'info' );
    $vessel_ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $vessel_ids ) ) {
        yatco_log( 'Stage 1: Error getting vessel IDs: ' . $vessel_ids->get_error_message(), 'error' );
        set_transient( 'yatco_cache_warming_status', 'Stage 1 Error: ' . $vessel_ids->get_error_message(), 60 );
        return;
    }
    
    $total = count( $vessel_ids );
    yatco_log( "Stage 1: Found {$total} vessel IDs", 'info' );
    set_transient( 'yatco_cache_warming_status', "Stage 1: Processing {$total} vessel IDs...", 600 );
    
    // Get progress
    $progress = get_transient( 'yatco_stage1_progress' );
    $start_from = 0;
    $processed_ids = array();
    
    if ( $progress !== false && is_array( $progress ) ) {
        $start_from = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        $processed_ids = isset( $progress['processed_ids'] ) ? $progress['processed_ids'] : array();
        $vessel_ids = array_slice( $vessel_ids, $start_from );
        yatco_log( "Stage 1: Resuming from position {$start_from}", 'info' );
    } else {
        yatco_log( 'Stage 1: Starting fresh import', 'info' );
    }
    
    $processed = 0;
    $batch_size = 25; // Reduced to 25 to prevent server overload
    $delay_seconds = 3; // Increased to 3 seconds between batches for safety
    $delay_between_items = 0.1; // 100ms delay between individual items
    
    yatco_log( "Stage 1: Batch size: {$batch_size}, Delay between batches: {$delay_seconds}s, Delay between items: {$delay_between_items}s", 'info' );
    
    $batch_num = 0;
    foreach ( array_chunk( $vessel_ids, $batch_size ) as $batch ) {
        $batch_num++;
        yatco_log( "Stage 1: Processing batch {$batch_num} with " . count( $batch ) . " vessels", 'info' );
        
        // Check stop flag
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        if ( $stop_flag !== false ) {
            yatco_log( 'Stage 1: Stop flag detected in batch, cancelling', 'warning' );
            delete_transient( 'yatco_cache_warming_stop' );
            delete_transient( 'yatco_stage1_progress' );
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
            
            // Check stop flag before API call (in case it was set during delay)
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                yatco_log( 'Stage 1: Stop flag detected before API call, cancelling', 'warning' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_stage1_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
                return;
            }
            
            // Fetch lightweight data (just Result section for name)
            $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll';
            yatco_log( "Stage 1: Fetching vessel {$vessel_id} from API", 'debug' );
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
            
            // Check stop flag after API call (in case it was set during the request)
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                yatco_log( 'Stage 1: Stop flag detected after API call, cancelling', 'warning' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_stage1_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
                return;
            }
            
            if ( is_wp_error( $response ) ) {
                yatco_log( "Stage 1: WP_Error for vessel {$vessel_id}: " . $response->get_error_message(), 'error' );
                continue; // Skip on error
            }
            
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code !== 200 ) {
                yatco_log( "Stage 1: HTTP {$response_code} for vessel {$vessel_id}", 'error' );
                continue; // Skip on error
            }
            
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $data ) || ! isset( $data['Result'] ) ) {
                yatco_log( "Stage 1: No data or missing Result for vessel {$vessel_id}", 'warning' );
                continue; // Skip if no data
            }
            
            $result = $data['Result'];
            $basic = isset( $data['BasicInfo'] ) ? $data['BasicInfo'] : array();
            
            // Vessel name: Prefer BasicInfo.BoatName (better case formatting), then Result.VesselName
            // BasicInfo.BoatName usually has proper case like "All Ocean Yachts 100' Fiberglass"
            // Result.VesselName is often all caps like "ALL OCEAN YACHTS 100′ FIBERGLASS"
            $name = '';
            if ( ! empty( $basic['BoatName'] ) ) {
                $name = $basic['BoatName'];
            } elseif ( ! empty( $result['VesselName'] ) ) {
                $name = $result['VesselName'];
            } else {
                $name = 'Vessel ' . $vessel_id;
            }
            
            $mlsid = isset( $result['MLSID'] ) ? $result['MLSID'] : $vessel_id;
            
            // Find or create post - use vessel name exactly as provided by API
            $post_id = yatco_find_or_create_vessel_post( $vessel_id, $mlsid, $name );
            
            if ( $post_id ) {
                // Store minimal data
                update_post_meta( $post_id, 'yacht_vessel_id', $vessel_id );
                update_post_meta( $post_id, 'yacht_mlsid', $mlsid );
                update_post_meta( $post_id, 'yacht_import_stage', 1 );
                update_post_meta( $post_id, 'yacht_last_updated', time() );
                
                $processed_ids[] = $vessel_id;
                $processed++;
                yatco_log( "Stage 1: Processed vessel {$vessel_id} (Post ID: {$post_id})", 'debug' );
            } else {
                yatco_log( "Stage 1: Failed to create/find post for vessel {$vessel_id}", 'error' );
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
        yatco_log( "Stage 1: Saving progress - {$current_total}/{$total} ({$percent}%)", 'info' );
        set_transient( 'yatco_stage1_progress', $progress_data, 3600 );
        set_transient( 'yatco_cache_warming_status', "Stage 1: Processed {$current_total} of {$total} vessels ({$percent}%)...", 600 );
        
        // Delay between batches
        if ( $processed < $total ) {
            yatco_log( "Stage 1: Waiting {$delay_seconds} seconds before next batch", 'debug' );
            sleep( $delay_seconds );
        }
    }
    
    // Store all vessel IDs for later stages
    update_option( 'yatco_stage1_vessel_ids', $processed_ids, false );
    yatco_log( "Stage 1: Stored " . count( $processed_ids ) . " vessel IDs for later stages", 'info' );
    
    // Clear progress
    delete_transient( 'yatco_stage1_progress' );
    set_transient( 'yatco_cache_warming_status', "Stage 1 Complete: Processed {$processed} vessels. Ready for Stage 2.", 300 );
    yatco_log( "Stage 1: Complete! Processed {$processed} vessels total", 'info' );
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
            yatco_log( 'Stage 2: Stop flag detected in batch, cancelling', 'warning' );
            delete_transient( 'yatco_cache_warming_stop' );
            delete_transient( 'yatco_stage2_progress' );
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
            yatco_log( 'Stage 3: Stop flag detected in batch, cancelling', 'warning' );
            delete_transient( 'yatco_cache_warming_stop' );
            delete_transient( 'yatco_stage3_progress' );
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
            
            // Check stop flag before calling import function
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                yatco_log( 'Stage 3: Stop flag detected before import, cancelling', 'warning' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_stage3_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                return;
            }
            
            // Use existing import function for full data
            $import_result = yatco_import_single_vessel( $token, $vessel_id );
            
            // Check stop flag after import (in case it was set during the import)
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                yatco_log( 'Stage 3: Stop flag detected after import, cancelling', 'warning' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_stage3_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                return;
            }
            
            if ( ! is_wp_error( $import_result ) ) {
                $post_id = $import_result;
                update_post_meta( $post_id, 'yacht_import_stage', 3 );
                $processed++;
            } elseif ( $import_result->get_error_code() === 'import_stopped' ) {
                // Import was stopped, exit immediately
                yatco_log( 'Stage 3: Import stopped during vessel processing', 'warning' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_stage3_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                return;
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
 * Full Import: Import all active vessels with complete data.
 * This replaces the 3-stage import with a single unified import.
 */
function yatco_full_import( $token ) {
    yatco_log( 'Full Import: Starting', 'info' );
    
    // Check stop flag (check both option and transient for reliability)
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( 'Full Import: Stop flag detected, cancelling', 'warning' );
        delete_option( 'yatco_import_stop_flag' );
        delete_transient( 'yatco_cache_warming_stop' );
        delete_transient( 'yatco_import_progress' );
        set_transient( 'yatco_cache_warming_status', 'Full Import cancelled.', 60 );
        return;
    }
    
    set_transient( 'yatco_cache_warming_status', 'Full Import: Fetching vessel IDs...', 600 );
    yatco_log( 'Full Import: Fetching all active vessel IDs', 'info' );
    
    // Fetch all active vessel IDs
    $vessel_ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $vessel_ids ) ) {
        set_transient( 'yatco_cache_warming_status', 'Full Import Error: ' . $vessel_ids->get_error_message(), 60 );
        yatco_log( 'Full Import Error: Failed to fetch active vessel IDs: ' . $vessel_ids->get_error_message(), 'error' );
        return;
    }
    
    $total = count( $vessel_ids );
    yatco_log( "Full Import: Found {$total} vessel IDs from API", 'info' );
    
    // Store vessel IDs for daily sync
    update_option( 'yatco_vessel_ids', $vessel_ids, false );
    
    // Get progress
    $progress = get_transient( 'yatco_import_progress' );
    $start_from = 0;
    $skip_existing = false;
    
    if ( $progress !== false && is_array( $progress ) ) {
        // Resuming from previous run
        $start_from = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        $vessel_ids = array_slice( $vessel_ids, $start_from );
        yatco_log( "Full Import: Resuming from position {$start_from}", 'info' );
    } else {
        // Fresh import - check which vessels already exist to skip them
        yatco_log( 'Full Import: Starting fresh import - checking for existing vessels...', 'info' );
        set_transient( 'yatco_cache_warming_status', 'Full Import: Checking for existing vessels...', 600 );
        
        $skip_existing = true;
    }
    
    // Build lookup map for existing vessels to avoid expensive queries during import
    // This is a one-time query that builds vessel_id -> post_id and mlsid -> post_id maps
    set_transient( 'yatco_cache_warming_status', 'Full Import: Building vessel lookup map...', 600 );
    yatco_log( 'Full Import: Building vessel lookup map for faster imports', 'info' );
    
    global $wpdb;
    $vessel_id_lookup = array();
    $mlsid_lookup = array();
    
    // Get all existing yacht posts with their vessel IDs in one query
    $vessel_id_posts = $wpdb->get_results( $wpdb->prepare( "
        SELECT pm.post_id, pm.meta_value as vessel_id
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type = %s
        AND pm.meta_key = 'yacht_vessel_id'
        AND pm.meta_value != ''
        AND pm.meta_value IS NOT NULL
    ", 'yacht' ) );
    
    foreach ( $vessel_id_posts as $post ) {
        if ( ! empty( $post->vessel_id ) ) {
            $vessel_id_lookup[ intval( $post->vessel_id ) ] = intval( $post->post_id );
        }
    }
    
    // Get all existing yacht posts with their MLSIDs in one query
    $mlsid_posts = $wpdb->get_results( $wpdb->prepare( "
        SELECT pm.post_id, pm.meta_value as mlsid
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type = %s
        AND pm.meta_key = 'yacht_mlsid'
        AND pm.meta_value != ''
        AND pm.meta_value IS NOT NULL
    ", 'yacht' ) );
    
    foreach ( $mlsid_posts as $post ) {
        if ( ! empty( $post->mlsid ) ) {
            $mlsid_lookup[ $post->mlsid ] = intval( $post->post_id );
        }
    }
    
    yatco_log( "Full Import: Built lookup map with " . count( $vessel_id_lookup ) . " vessel IDs and " . count( $mlsid_lookup ) . " MLSIDs", 'info' );
    
    // On fresh import, filter out vessels that already exist
    if ( $skip_existing ) {
        $original_count = count( $vessel_ids );
        $vessel_ids_to_process = array();
        
        foreach ( $vessel_ids as $vessel_id ) {
            $vessel_id_int = intval( $vessel_id );
            // Skip if vessel ID already exists in lookup map
            if ( ! isset( $vessel_id_lookup[ $vessel_id_int ] ) ) {
                $vessel_ids_to_process[] = $vessel_id;
            }
        }
        
        $skipped_count = $original_count - count( $vessel_ids_to_process );
        $vessel_ids = $vessel_ids_to_process;
        
        yatco_log( "Full Import: Skipped {$skipped_count} existing vessels, {$original_count} total found, " . count( $vessel_ids ) . " new to process", 'info' );
        set_transient( 'yatco_cache_warming_status', "Full Import: Skipped {$skipped_count} existing vessels, processing " . count( $vessel_ids ) . " new vessels...", 600 );
    }
    
    $total_to_process = count( $vessel_ids );
    if ( $total_to_process === 0 ) {
        yatco_log( 'Full Import: No new vessels to process. All vessels are already imported.', 'info' );
        delete_transient( 'yatco_import_progress' );
        set_transient( 'yatco_cache_warming_status', 'Full Import Complete: All vessels are already imported.', 300 );
        return;
    }
    
    set_transient( 'yatco_cache_warming_status', "Full Import: Processing {$total_to_process} vessels...", 600 );
    
    $processed = 0;
    $batch_size = 2; // Process 2 at a time (balanced between speed and stability)
    $delay_seconds = 3; // 3 second delay between batches (reduced to speed up)
    $delay_between_items = 0.5; // 0.5 second delay between individual items (reduced from 2 seconds)
    
    yatco_log( "Full Import: Processing {$total_to_process} vessels. Batch size: {$batch_size}, Delay between batches: {$delay_seconds}s, Delay between items: {$delay_between_items}s", 'info' );
    
    foreach ( array_chunk( $vessel_ids, $batch_size ) as $batch ) {
        // Check stop flag (check both option and transient)
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        }
        if ( $stop_flag !== false ) {
            yatco_log( 'Full Import: Stop flag detected in batch, cancelling', 'warning' );
            delete_option( 'yatco_import_stop_flag' );
            delete_transient( 'yatco_cache_warming_stop' );
            delete_transient( 'yatco_import_progress' );
            set_transient( 'yatco_cache_warming_status', 'Full Import stopped by user.', 60 );
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag (check both option and transient)
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
                $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( 'Full Import: Stop flag detected, cancelling', 'warning' );
                delete_option( 'yatco_import_stop_flag' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_import_progress' );
                set_transient( 'yatco_cache_warming_status', 'Full Import stopped by user.', 60 );
                return;
            }
            
            // Flush output so stop button can be processed (when running directly)
            if ( ob_get_level() > 0 ) {
                ob_flush();
            }
            flush();
            
            // Small delay between items - check stop flag during delay
            if ( $delay_between_items > 0 ) {
                // Break delay into smaller chunks and check stop flag between chunks
                $chunk_size = 100000; // 100ms chunks
                $total_chunks = ( $delay_between_items * 1000000 ) / $chunk_size;
                for ( $i = 0; $i < $total_chunks; $i++ ) {
                    usleep( $chunk_size );
                    // Check stop flag every chunk (check both option and transient)
                    $stop_flag = get_option( 'yatco_import_stop_flag', false );
                    if ( $stop_flag === false ) {
                        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
                    }
                    if ( $stop_flag !== false ) {
                        yatco_log( 'Full Import: Stop flag detected during delay, cancelling', 'warning' );
                        delete_option( 'yatco_import_stop_flag' );
                        delete_transient( 'yatco_cache_warming_stop' );
                        delete_transient( 'yatco_import_progress' );
                        set_transient( 'yatco_cache_warming_status', 'Full Import stopped by user.', 60 );
                        return;
                    }
                }
            }
            
            // Import full vessel data
            yatco_log( "Full Import: Processing vessel {$vessel_id}", 'debug' );
            $import_result = yatco_import_single_vessel( $token, $vessel_id, $vessel_id_lookup, $mlsid_lookup );
            
            // Check stop flag after import (check both option and transient)
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
                $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( 'Full Import: Stop flag detected after import, cancelling', 'warning' );
                delete_option( 'yatco_import_stop_flag' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_import_progress' );
                set_transient( 'yatco_cache_warming_status', 'Full Import stopped by user.', 60 );
                return;
            }
            
            // Flush output after each vessel (when running directly)
            if ( ob_get_level() > 0 ) {
                ob_flush();
            }
            flush();
            
            if ( ! is_wp_error( $import_result ) ) {
                $processed++;
                yatco_log( "Full Import: Successfully imported vessel {$vessel_id}", 'debug' );
            } elseif ( $import_result->get_error_code() === 'import_stopped' ) {
                yatco_log( 'Full Import: Import stopped during vessel processing', 'warning' );
                delete_option( 'yatco_import_stop_flag' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_transient( 'yatco_import_progress' );
                set_transient( 'yatco_cache_warming_status', 'Full Import stopped by user.', 60 );
                return;
            } else {
                yatco_log( "Full Import: Error importing vessel {$vessel_id}: " . $import_result->get_error_message(), 'error' );
            }
            
            // Memory cleanup after each vessel to prevent memory buildup
            if ( function_exists( 'gc_collect_cycles' ) ) {
                gc_collect_cycles();
            }
        }
        
        // Save progress
        $current_total = $start_from + $processed;
        $percent = $total_to_process > 0 ? round( ( $processed / $total_to_process ) * 100, 1 ) : 0;
        $progress_data = array(
            'last_processed' => $current_total,
            'total'         => $total_to_process,
            'timestamp'     => time(),
            'percent'       => $percent,
        );
        set_transient( 'yatco_import_progress', $progress_data, 3600 );
        set_transient( 'yatco_cache_warming_status', "Full Import: Processed {$processed} of {$total_to_process} vessels ({$percent}%)...", 600 );
        yatco_log( "Full Import: Progress - {$processed}/{$total_to_process} ({$percent}%)", 'info' );
        
        // Flush output after each batch (when running directly)
        if ( ob_get_level() > 0 ) {
            ob_flush();
        }
        flush();
        
        // Memory cleanup after each batch
        if ( function_exists( 'gc_collect_cycles' ) ) {
            gc_collect_cycles();
        }
        
        // Delay between batches - check stop flag during delay
        if ( $processed < $total_to_process ) {
            // Break delay into 1-second chunks and check stop flag between chunks
            for ( $i = 0; $i < $delay_seconds; $i++ ) {
                sleep( 1 );
                // Check stop flag every second (check both option and transient)
                $stop_flag = get_option( 'yatco_import_stop_flag', false );
                if ( $stop_flag === false ) {
                    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
                }
                if ( $stop_flag !== false ) {
                    yatco_log( 'Full Import: Stop flag detected during batch delay, cancelling', 'warning' );
                    delete_option( 'yatco_import_stop_flag' );
                    delete_transient( 'yatco_cache_warming_stop' );
                    delete_transient( 'yatco_import_progress' );
                    set_transient( 'yatco_cache_warming_status', 'Full Import stopped by user.', 60 );
                    return;
                }
            }
        }
    }
    
    // Clear progress
    delete_transient( 'yatco_import_progress' );
    set_transient( 'yatco_cache_warming_status', "Full Import Complete: Processed {$processed} vessels.", 300 );
    yatco_log( "Full Import Complete: Processed {$processed} vessels.", 'info' );
}

/**
 * Daily Sync: Check for new/removed vessels and update prices/days on market.
 */
function yatco_daily_sync_check( $token ) {
    yatco_log( 'Daily Sync: Starting', 'info' );
    set_transient( 'yatco_cache_warming_status', 'Daily Sync: Checking for changes...', 600 );
    
    // Initialize progress tracking
    $sync_progress = array(
        'stage' => 'daily_sync',
        'step' => 'fetching_ids',
        'processed' => 0,
        'total' => 0,
        'timestamp' => time(),
    );
    set_transient( 'yatco_daily_sync_progress', $sync_progress, 3600 );
    
    // Get current active vessel IDs from API
    set_transient( 'yatco_cache_warming_status', 'Daily Sync: Fetching active vessel IDs...', 600 );
    $current_ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $current_ids ) ) {
        set_transient( 'yatco_cache_warming_status', 'Daily Sync Error: ' . $current_ids->get_error_message(), 60 );
        delete_transient( 'yatco_daily_sync_progress' );
        yatco_log( 'Daily Sync Error: ' . $current_ids->get_error_message(), 'error' );
        return;
    }
    
    // Get stored vessel IDs
    $stored_ids = get_option( 'yatco_vessel_ids', array() );
    
    // Find removed vessels
    $removed_ids = array_diff( $stored_ids, $current_ids );
    
    // Find new vessels
    $new_ids = array_diff( $current_ids, $stored_ids );
    
    // Find existing vessels (for price/days on market updates)
    $existing_ids = array_intersect( $current_ids, $stored_ids );
    
    // Update stored IDs
    update_option( 'yatco_vessel_ids', $current_ids, false );
    
    $total_steps = count( $removed_ids ) + count( $new_ids ) + count( $existing_ids );
    $sync_progress['total'] = $total_steps;
    $sync_progress['step'] = 'processing';
    set_transient( 'yatco_daily_sync_progress', $sync_progress, 3600 );
    set_transient( 'yatco_cache_warming_status', "Daily Sync: Processing {$total_steps} vessels...", 600 );
    
    // Mark removed vessels as draft
    $removed_count = 0;
    $processed = 0;
    foreach ( $removed_ids as $vessel_id ) {
        $post_id = yatco_find_vessel_post_by_id( $vessel_id );
        if ( $post_id ) {
            wp_update_post( array(
                'ID' => $post_id,
                'post_status' => 'draft',
            ) );
            update_post_meta( $post_id, 'yacht_removed_from_api', true );
            update_post_meta( $post_id, 'yacht_removed_date', time() );
            $removed_count++;
        }
        $processed++;
        $sync_progress['processed'] = $processed;
        $sync_progress['removed'] = $removed_count;
        set_transient( 'yatco_daily_sync_progress', $sync_progress, 3600 );
        set_transient( 'yatco_cache_warming_status', "Daily Sync: Processing removed vessels ({$processed}/{$total_steps})...", 600 );
    }
    
    // Import new vessels
    $new_count = 0;
    if ( ! empty( $new_ids ) ) {
        yatco_log( "Daily Sync: Found " . count( $new_ids ) . " new vessels to import", 'info' );
        set_transient( 'yatco_cache_warming_status', "Daily Sync: Importing " . count( $new_ids ) . " new vessels...", 600 );
        foreach ( $new_ids as $vessel_id ) {
            $import_result = yatco_import_single_vessel( $token, $vessel_id );
            if ( ! is_wp_error( $import_result ) ) {
                $new_count++;
            }
            $processed++;
            $sync_progress['processed'] = $processed;
            $sync_progress['new'] = $new_count;
            set_transient( 'yatco_daily_sync_progress', $sync_progress, 3600 );
            set_transient( 'yatco_cache_warming_status', "Daily Sync: Importing new vessels ({$processed}/{$total_steps})...", 600 );
            // Small delay between imports
            usleep( 500000 ); // 500ms
        }
    }
    
    // Check and update prices and days on market for existing vessels
    $price_updates = 0;
    $days_on_market_updates = 0;
    $batch_size = 50; // Process 50 at a time
    $delay_seconds = 1; // 1 second delay between batches
    
    if ( ! empty( $existing_ids ) ) {
        yatco_log( "Daily Sync: Checking prices and days on market for " . count( $existing_ids ) . " existing vessels", 'info' );
        set_transient( 'yatco_cache_warming_status', 'Daily Sync: Updating prices and days on market...', 600 );
        
        $existing_total = count( $existing_ids );
        $existing_processed = 0;
        
        foreach ( array_chunk( $existing_ids, $batch_size ) as $batch ) {
            // Check stop flag
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            if ( $stop_flag !== false ) {
                yatco_log( 'Daily Sync: Stop flag detected, cancelling', 'warning' );
                break;
            }
            
            foreach ( $batch as $vessel_id ) {
                $post_id = yatco_find_vessel_post_by_id( $vessel_id );
                if ( ! $post_id ) {
                    continue;
                }
                
                // Fetch lightweight data to check price and days on market
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
                $basic = isset( $data['BasicInfo'] ) ? $data['BasicInfo'] : array();
                $misc = isset( $data['MiscInfo'] ) ? $data['MiscInfo'] : array();
                
                // Check and update price
                $api_price_usd = null;
                if ( isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ) {
                    $api_price_usd = floatval( $basic['AskingPriceUSD'] );
                } elseif ( isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
                    $api_price_usd = floatval( $result['AskingPriceCompare'] );
                }
                
                if ( $api_price_usd !== null ) {
                    $stored_price = get_post_meta( $post_id, 'yacht_price_usd', true );
                    $stored_price = ! empty( $stored_price ) ? floatval( $stored_price ) : null;
                    
                    // Check if price changed
                    if ( $stored_price === null || abs( $api_price_usd - $stored_price ) > 0.01 ) {
                        // Price changed - update it
                        $price_formatted = isset( $result['AskingPriceFormatted'] ) ? $result['AskingPriceFormatted'] : '';
                        update_post_meta( $post_id, 'yacht_price_usd', $api_price_usd );
                        update_post_meta( $post_id, 'yacht_price', $price_formatted );
                        update_post_meta( $post_id, 'yacht_last_updated', time() );
                        $price_updates++;
                        yatco_log( "Daily Sync: Updated price for vessel {$vessel_id} from {$stored_price} to {$api_price_usd}", 'info' );
                    }
                }
                
                // Check and update days on market
                $api_days_on_market = null;
                if ( isset( $result['DaysOnMarket'] ) && $result['DaysOnMarket'] > 0 ) {
                    $api_days_on_market = intval( $result['DaysOnMarket'] );
                } elseif ( isset( $misc['DaysOnMarket'] ) && $misc['DaysOnMarket'] > 0 ) {
                    $api_days_on_market = intval( $misc['DaysOnMarket'] );
                }
                
                if ( $api_days_on_market !== null ) {
                    $stored_days = get_post_meta( $post_id, 'yacht_days_on_market', true );
                    $stored_days = ! empty( $stored_days ) ? intval( $stored_days ) : null;
                    
                    // Check if days on market changed
                    if ( $stored_days === null || $api_days_on_market !== $stored_days ) {
                        update_post_meta( $post_id, 'yacht_days_on_market', $api_days_on_market );
                        update_post_meta( $post_id, 'yacht_last_updated', time() );
                        $days_on_market_updates++;
                        yatco_log( "Daily Sync: Updated days on market for vessel {$vessel_id} from {$stored_days} to {$api_days_on_market}", 'info' );
                    }
                }
                
                $existing_processed++;
                $processed++;
                $sync_progress['processed'] = $processed;
                $sync_progress['price_updates'] = $price_updates;
                $sync_progress['days_on_market_updates'] = $days_on_market_updates;
                $percent = $total_steps > 0 ? round( ( $processed / $total_steps ) * 100, 1 ) : 0;
                set_transient( 'yatco_daily_sync_progress', $sync_progress, 3600 );
                set_transient( 'yatco_cache_warming_status', "Daily Sync: Checking existing vessels ({$existing_processed}/{$existing_total}) - {$percent}% complete...", 600 );
                
                // Small delay between items
                usleep( 200000 ); // 200ms
            }
            
            // Delay between batches
            if ( count( $batch ) === $batch_size ) {
                sleep( $delay_seconds );
            }
        }
    }
    
    // Store sync results
    $sync_results = array(
        'removed' => $removed_count,
        'new' => $new_count,
        'price_updates' => $price_updates,
        'days_on_market_updates' => $days_on_market_updates,
        'timestamp' => time(),
    );
    update_option( 'yatco_daily_sync_results', $sync_results, false );
    update_option( 'yatco_daily_sync_last_run', time(), false );
    
    $status_message = sprintf(
        'Daily Sync Complete: %d removed, %d new, %d price updates, %d days on market updates.',
        $removed_count,
        $new_count,
        $price_updates,
        $days_on_market_updates
    );
    
    // Clear progress and set final status
    delete_transient( 'yatco_daily_sync_progress' );
    set_transient( 'yatco_cache_warming_status', $status_message, 300 );
    yatco_log( $status_message, 'info' );
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

