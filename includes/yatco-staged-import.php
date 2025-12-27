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
    
    // Save logs - use false for autoload to bypass object cache
    update_option( 'yatco_import_logs', $logs, false );
    
    // Force immediate cache flush to ensure AJAX handlers see the latest logs
    wp_cache_delete( 'yatco_import_logs', 'options' );
    wp_cache_flush();
    
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
    
    // Check stop flag - check both option and transient (consistent with full import)
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( '🛑 Stage 1: Stop flag detected, cancelling', 'warning' );
        delete_transient( 'yatco_stage1_progress' );
        set_transient( 'yatco_cache_warming_status', 'Stage 1 cancelled.', 60 );
        // DON'T delete stop flag - keep it so it can be checked again
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
        
        // Check stop flag - check both option and transient
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        }
        if ( $stop_flag !== false ) {
            yatco_log( '🛑 Stage 1: Stop flag detected in batch, cancelling', 'warning' );
            delete_transient( 'yatco_stage1_progress' );
            set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
            // DON'T delete stop flag - keep it so it can be checked again
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Stage 1: Stop flag detected, cancelling', 'warning' );
                set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
                return;
            }
            
            // Small delay between items to prevent server overload
            if ( $delay_between_items > 0 ) {
                usleep( $delay_between_items * 1000000 ); // Convert to microseconds
            }
            
            // Check stop flag before API call - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Stage 1: Stop flag detected before API call, cancelling', 'warning' );
                delete_transient( 'yatco_stage1_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
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
            
            // Check stop flag after API call - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Stage 1: Stop flag detected after API call, cancelling', 'warning' );
                delete_transient( 'yatco_stage1_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 1 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
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
    // Check stop flag - check both option and transient (consistent with full import)
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( '🛑 Stage 2: Stop flag detected, cancelling', 'warning' );
        delete_transient( 'yatco_stage2_progress' );
        set_transient( 'yatco_cache_warming_status', 'Stage 2 cancelled.', 60 );
        // DON'T delete stop flag - keep it so it can be checked again
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
        // Check stop flag - check both option and transient
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        }
        if ( $stop_flag !== false ) {
            yatco_log( '🛑 Stage 2: Stop flag detected in batch, cancelling', 'warning' );
            delete_transient( 'yatco_stage2_progress' );
            set_transient( 'yatco_cache_warming_status', 'Stage 2 stopped by user.', 60 );
            // DON'T delete stop flag - keep it so it can be checked again
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Stage 2: Stop flag detected, cancelling', 'warning' );
                set_transient( 'yatco_cache_warming_status', 'Stage 2 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
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
    // Check stop flag - check both option and transient (consistent with full import)
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( '🛑 Stage 3: Stop flag detected, cancelling', 'warning' );
        delete_transient( 'yatco_stage3_progress' );
        set_transient( 'yatco_cache_warming_status', 'Stage 3 cancelled.', 60 );
        // DON'T delete stop flag - keep it so it can be checked again
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
        // Check stop flag - check both option and transient
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        }
        if ( $stop_flag !== false ) {
            yatco_log( '🛑 Stage 3: Stop flag detected in batch, cancelling', 'warning' );
            delete_transient( 'yatco_stage3_progress' );
            set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
            // DON'T delete stop flag - keep it so it can be checked again
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Stage 3: Stop flag detected, cancelling', 'warning' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
                return;
            }
            
            // Small delay between items to prevent server overload
            if ( $delay_between_items > 0 ) {
                usleep( $delay_between_items * 1000000 ); // Convert to microseconds
            }
            
            // Check stop flag before calling import function - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Stage 3: Stop flag detected before import, cancelling', 'warning' );
                delete_transient( 'yatco_stage3_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
                return;
            }
            
            // Use existing import function for full data
            $import_result = yatco_import_single_vessel( $token, $vessel_id );
            
            // Check stop flag after import - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Stage 3: Stop flag detected after import, cancelling', 'warning' );
                delete_transient( 'yatco_stage3_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
                return;
            }
            
            if ( ! is_wp_error( $import_result ) ) {
                $post_id = $import_result;
                update_post_meta( $post_id, 'yacht_import_stage', 3 );
                $processed++;
            } elseif ( $import_result->get_error_code() === 'import_stopped' ) {
                // Import was stopped, exit immediately
                yatco_log( '🛑 Stage 3: Import stopped during vessel processing', 'warning' );
                delete_transient( 'yatco_stage3_progress' );
                set_transient( 'yatco_cache_warming_status', 'Stage 3 stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
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
    // Load progress helper functions
    require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
    
    yatco_log( 'Full Import: Starting', 'info' );
    
    // Increase execution time and memory limits at start
    @set_time_limit( 0 ); // Try unlimited (may not work on all servers)
    @ini_set( 'max_execution_time', 0 );
    @ini_set( 'memory_limit', '512M' );
    
    // Register shutdown handler to detect fatal errors, timeouts, and other stop conditions
    register_shutdown_function( function() {
        require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
        $error = error_get_last();
        $progress = yatco_get_import_status( 'full' );
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        
        // Check if this was a user-initiated stop
        if ( $stop_flag !== false ) {
            return; // User stopped it, don't log as error
        }
        
        // Check for fatal PHP errors
        if ( $error !== null && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ) ) ) {
            $error_msg = $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
            yatco_log( 'Full Import: FATAL ERROR - ' . $error_msg, 'error' );
            
            if ( $progress !== false && is_array( $progress ) ) {
                $processed = isset( $progress['processed'] ) ? $progress['processed'] : 0;
                $total = isset( $progress['total'] ) ? $progress['total'] : 0;
                yatco_log( "Full Import: Stopped due to fatal error at {$processed}/{$total} vessels", 'error' );
                yatco_update_import_status_message( 'Full Import FATAL ERROR: ' . $error['message'] . ' - Stopped at ' . $processed . '/' . $total . ' vessels. Progress saved, auto-resume enabled.' );
            } else {
                yatco_update_import_status_message( 'Full Import FATAL ERROR: ' . $error['message'] );
            }
            delete_option( 'yatco_import_lock' ); // Release lock
            delete_option( 'yatco_import_process_id' ); // Release process ID
            return;
        }
        
        // Check if connection was aborted (browser closed, timeout, etc.)
        if ( connection_aborted() ) {
            if ( $progress !== false && is_array( $progress ) ) {
                $processed = isset( $progress['processed'] ) ? intval( $progress['processed'] ) : ( isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0 );
                $total = isset( $progress['total'] ) ? intval( $progress['total'] ) : 0;
                $last_vessel = isset( $progress['last_vessel_id'] ) ? $progress['last_vessel_id'] : 'unknown';
                yatco_log( "Full Import: CONNECTION ABORTED (shutdown handler) - Stopped unexpectedly at {$processed}/{$total} vessels (last vessel: {$last_vessel})", 'error' );
                
                // Ensure auto-resume is enabled
                update_option( 'yatco_import_auto_resume', time(), false );
                yatco_update_import_status_message( "Full Import: Connection aborted/lost. Stopped at {$processed}/{$total} vessels (last: {$last_vessel}). Auto-resume enabled." );
                yatco_log( "Full Import: Auto-resume flag set due to connection abort", 'info' );
            } else {
                yatco_log( 'Full Import: CONNECTION ABORTED (shutdown handler) - Stopped unexpectedly (no progress data available)', 'error' );
                update_option( 'yatco_import_auto_resume', time(), false );
                yatco_update_import_status_message( 'Full Import: Connection aborted/lost. Auto-resume enabled.' );
            }
            delete_option( 'yatco_import_lock' ); // Release lock
            delete_option( 'yatco_import_process_id' ); // Release process ID
            return;
        }
        
        // Check if we hit execution time limit
        $max_execution_time = ini_get( 'max_execution_time' );
        if ( $max_execution_time > 0 ) {
            $time_elapsed = time() - $_SERVER['REQUEST_TIME'];
            if ( $time_elapsed >= ( $max_execution_time - 1 ) ) {
                if ( $progress !== false && is_array( $progress ) ) {
                    $processed = isset( $progress['processed'] ) ? $progress['processed'] : 0;
                    $total = isset( $progress['total'] ) ? $progress['total'] : 0;
                    $last_vessel = isset( $progress['last_vessel_id'] ) ? $progress['last_vessel_id'] : 'unknown';
                    yatco_log( "Full Import: EXECUTION TIME LIMIT REACHED - Max execution time ({$max_execution_time}s) exceeded. Stopped at {$processed}/{$total} vessels (last vessel: {$last_vessel})", 'error' );
                    yatco_update_import_status_message( "Full Import: Execution time limit ({$max_execution_time}s) reached. Stopped at {$processed}/{$total} vessels (last: {$last_vessel}). Auto-resume enabled." );
                } else {
                    yatco_log( "Full Import: EXECUTION TIME LIMIT REACHED - Max execution time ({$max_execution_time}s) exceeded", 'error' );
                    yatco_update_import_status_message( "Full Import: Execution time limit ({$max_execution_time}s) reached. Auto-resume enabled." );
                }
                delete_option( 'yatco_import_lock' ); // Release lock
                delete_option( 'yatco_import_process_id' ); // Release process ID
                return;
            }
        }
        
        // Check memory usage
        if ( function_exists( 'memory_get_usage' ) && function_exists( 'memory_get_peak_usage' ) ) {
            $memory_limit = ini_get( 'memory_limit' );
            $current_memory = memory_get_usage( true );
            $peak_memory = memory_get_peak_usage( true );
            
            // Convert memory_limit to bytes for comparison
            $memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
            
            if ( $peak_memory > ( $memory_limit_bytes * 0.9 ) ) {
                $memory_mb = round( $peak_memory / 1024 / 1024, 2 );
                $limit_mb = round( $memory_limit_bytes / 1024 / 1024, 2 );
                if ( $progress !== false && is_array( $progress ) ) {
                    $processed = isset( $progress['processed'] ) ? $progress['processed'] : 0;
                    $total = isset( $progress['total'] ) ? $progress['total'] : 0;
                    yatco_log( "Full Import: HIGH MEMORY USAGE - Peak memory: {$memory_mb}MB / {$limit_mb}MB limit. Stopped at {$processed}/{$total} vessels", 'error' );
                    yatco_update_import_status_message( "Full Import: High memory usage ({$memory_mb}MB/{$limit_mb}MB). Stopped at {$processed}/{$total} vessels. Auto-resume enabled." );
                } else {
                    yatco_log( "Full Import: HIGH MEMORY USAGE - Peak memory: {$memory_mb}MB / {$limit_mb}MB limit", 'error' );
                    yatco_update_import_status_message( "Full Import: High memory usage ({$memory_mb}MB/{$limit_mb}MB). Auto-resume enabled." );
                }
                delete_option( 'yatco_import_lock' ); // Release lock
                delete_option( 'yatco_import_process_id' ); // Release process ID
                return;
            }
        }
        
        // If we get here and progress exists but import is incomplete, log it as unexpected stop
        if ( $progress !== false && is_array( $progress ) ) {
            $processed = isset( $progress['processed'] ) ? $progress['processed'] : 0;
            $total = isset( $progress['total'] ) ? $progress['total'] : 0;
            if ( $total > 0 && $processed < $total ) {
                $last_vessel = isset( $progress['last_vessel_id'] ) ? $progress['last_vessel_id'] : 'unknown';
                yatco_log( "Full Import: UNEXPECTED STOP - Import stopped unexpectedly at {$processed}/{$total} vessels (last vessel: {$last_vessel}). No error detected, but import incomplete.", 'warning' );
                yatco_update_import_status_message( "Full Import: Unexpected stop at {$processed}/{$total} vessels (last: {$last_vessel}). Auto-resume enabled." );
            }
        }
    } );
    
    // Check stop flag (check both option and transient for reliability)
    // Check if import is already running (lock mechanism to prevent multiple imports)
    $import_lock = get_option( 'yatco_import_lock', false );
    if ( $import_lock !== false ) {
        $lock_time = intval( $import_lock );
        $lock_age = time() - $lock_time;
        // If lock is older than 10 minutes, assume the import process died and release the lock
        if ( $lock_age > 600 ) {
            yatco_log( "Full Import: Import lock expired (age: {$lock_age}s), releasing lock and continuing", 'warning' );
            delete_option( 'yatco_import_lock' );
        } else {
            yatco_log( "Full Import: Import already running (lock age: {$lock_age}s), skipping to prevent duplicate imports", 'info' );
            return; // Another import is already running, don't start a new one
        }
    }
    
    // Set import lock to prevent multiple imports from running simultaneously
    // Include process ID (PID) to track which import is running
    $process_id = getmypid() ?: uniqid( 'import_', true );
    update_option( 'yatco_import_lock', time(), false );
    update_option( 'yatco_import_process_id', $process_id, false );
    yatco_log( "Full Import: Import lock acquired (Process ID: {$process_id})", 'info' );
    
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( 'Full Import: Stop flag detected, cancelling', 'warning' );
        delete_option( 'yatco_import_stop_flag' );
        delete_transient( 'yatco_cache_warming_stop' );
        require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
        yatco_clear_import_status( 'full' );
        delete_option( 'yatco_import_lock' ); // Release lock
        delete_option( 'yatco_import_process_id' ); // Release process ID
        yatco_update_import_status_message( 'Full Import cancelled.' );
        return;
    }
    
    require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
    yatco_update_import_status_message( 'Full Import: Fetching vessel IDs...' );
    yatco_log( "Full Import: Fetching all active vessel IDs (Process ID: {$process_id})", 'info' );
    
    // Enable auto-resume if not already set (for fresh imports)
    $auto_resume = get_option( 'yatco_import_auto_resume', false );
    if ( $auto_resume === false ) {
        update_option( 'yatco_import_auto_resume', time(), false );
        yatco_log( 'Full Import: Auto-resume enabled', 'info' );
    }
    
    // Fetch all active vessel IDs
    $vessel_ids = yatco_get_active_vessel_ids( $token, 0 );
    
    // After fetching vessel IDs, verify we still own the lock
    $lock_process_id_after_fetch = get_option( 'yatco_import_process_id', false );
    // Use strval() comparison to handle string vs int PID differences (like other lock checks)
    if ( $lock_process_id_after_fetch !== false && strval( $lock_process_id_after_fetch ) !== strval( $process_id ) ) {
        yatco_log( "Full Import: Lock ownership changed after fetching vessel IDs ({$lock_process_id_after_fetch} vs {$process_id}), stopping", 'warning' );
        delete_option( 'yatco_import_lock' );
        delete_option( 'yatco_import_process_id' );
        return; // Another process took over, stop this one
    }
    
    if ( is_wp_error( $vessel_ids ) ) {
        delete_option( 'yatco_import_lock' ); // Release lock
        delete_option( 'yatco_import_process_id' ); // Release process ID
        require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
        yatco_update_import_status_message( 'Full Import Error: ' . $vessel_ids->get_error_message() );
        yatco_log( 'Full Import Error: Failed to fetch active vessel IDs: ' . $vessel_ids->get_error_message(), 'error' );
        return;
    }
    
    $total = count( $vessel_ids );
    yatco_log( "Full Import: Found {$total} vessel IDs from API", 'info' );
    
    // Store vessel IDs for daily sync
    update_option( 'yatco_vessel_ids', $vessel_ids, false );
    
    // Get progress from wp_options (migrated from transients)
    require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
    $progress = yatco_get_import_status( 'full' );
    $skip_existing = false;
    $resume_from_index = 0;
    
    if ( $progress !== false && is_array( $progress ) ) {
        // Resuming from previous run - use saved progress position
        $resume_from_index = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        yatco_log( "Full Import: Resuming from previously processed position: {$resume_from_index}", 'info' );
        // Note: We'll filter existing vessels AND skip already processed ones below
    } else {
        // Fresh import - check which vessels already exist to skip them
        yatco_log( 'Full Import: Starting fresh import - checking for existing vessels...', 'info' );
        require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
        yatco_update_import_status_message( 'Full Import: Checking for existing vessels...' );
        
        $skip_existing = true;
    }
    
    // Build lookup map for existing vessels to avoid expensive queries during import
    // This is a one-time query that builds vessel_id -> post_id and mlsid -> post_id maps
    yatco_update_import_status_message( 'Full Import: Building vessel lookup map...', 600 );
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
    
    $existing_count = count( $vessel_id_lookup );
    yatco_log( "Full Import: Built lookup map with {$existing_count} existing vessel IDs and " . count( $mlsid_lookup ) . " MLSIDs", 'info' );
    
    // Store original API count (before filtering) for progress tracking
    $total_from_api = count( $vessel_ids );
    
    // OPTIMIZED: Filter out vessels that already exist using array_diff (much faster than foreach loop)
    // Convert vessel IDs to integers and use array operations for speed
    $vessel_ids_int = array_map( 'intval', $vessel_ids ); // Convert all to integers once
    $existing_vessel_ids = array_keys( $vessel_id_lookup ); // Get array of existing vessel IDs (already integers)
    
    // Use array_diff to find vessels that need importing (vessels in API list but not in existing list)
    // This is MUCH faster than a foreach loop for large arrays
    $vessel_ids_to_process_int = array_diff( $vessel_ids_int, $existing_vessel_ids );
    
    // Convert back to original array format (preserve keys if needed)
    $vessel_ids_to_process = array_values( $vessel_ids_to_process_int );
    
    $already_imported_count = $total_from_api - count( $vessel_ids_to_process );
    $vessel_ids = $vessel_ids_to_process;
    
    if ( $skip_existing ) {
        yatco_log( "Full Import: Comparison complete - {$total_from_api} total vessels from API, {$already_imported_count} already imported, " . count( $vessel_ids ) . " new vessels need importing", 'info' );
        yatco_update_import_status_message( "Full Import: {$total_from_api} total from API, {$already_imported_count} already imported, " . count( $vessel_ids ) . " new to process..." );
    } else {
        yatco_log( "Full Import: Resuming - {$total_from_api} total vessels from API, {$already_imported_count} already imported, " . count( $vessel_ids ) . " remaining to process", 'info' );
        yatco_update_import_status_message( "Full Import: Resuming - " . count( $vessel_ids ) . " vessels remaining to process..." );
    }
    
    // If resuming, skip vessels we've already processed in previous runs
    if ( $resume_from_index > 0 ) {
        if ( count( $vessel_ids ) > $resume_from_index ) {
            $vessel_ids = array_slice( $vessel_ids, $resume_from_index );
            yatco_log( "Full Import: Resuming from position {$resume_from_index} - " . count( $vessel_ids ) . " vessels remaining to process", 'info' );
        } else {
            yatco_log( "Full Import: All vessels already processed (resume position {$resume_from_index} >= total " . count( $vessel_ids ) . ")", 'info' );
            delete_option( 'yatco_import_lock' ); // Release lock
            delete_option( 'yatco_import_process_id' ); // Release process ID
            yatco_clear_import_status( 'full' );
            yatco_update_import_status_message( 'Full Import Complete: All vessels have been processed.' );
            return;
        }
    }
    
    $total_to_process = count( $vessel_ids );
    if ( $total_to_process === 0 ) {
        yatco_log( 'Full Import: No new vessels to process. All vessels are already imported.', 'info' );
        delete_option( 'yatco_import_lock' ); // Release lock
        delete_option( 'yatco_import_process_id' ); // Release process ID
        yatco_clear_import_status( 'full' );
        yatco_update_import_status_message( 'Full Import Complete: All vessels are already imported.' );
        return;
    }
    
    yatco_update_import_status_message( "Full Import: Processing {$total_to_process} vessels..." );
    
    $processed = 0; // Count of successfully imported vessels
    $failed = 0;    // Count of failed vessel imports
    $attempted = 0; // Count of vessels attempted (for progress tracking)
    $batch_size = 2; // Process 2 at a time (balanced between speed and stability)
    $delay_seconds = 3; // 3 second delay between batches (reduced to speed up)
    $delay_between_items = 0.5; // 0.5 second delay between individual items (reduced from 2 seconds)
    
    yatco_log( "Full Import: Processing {$total_to_process} vessels. Batch size: {$batch_size}, Delay between batches: {$delay_seconds}s, Delay between items: {$delay_between_items}s", 'info' );
    
    $batch_number = 0;
    foreach ( array_chunk( $vessel_ids, $batch_size ) as $batch ) {
        $batch_number++;
        
        // CRITICAL: Check if this process still owns the lock before each batch
        // This prevents multiple imports from running simultaneously
        $lock_process_id = get_option( 'yatco_import_process_id', false );
        // Use loose comparison to handle string vs int PID differences
        if ( $lock_process_id !== false && strval( $lock_process_id ) !== strval( $process_id ) ) {
            yatco_log( "Full Import: Lock owned by different process ({$lock_process_id} vs {$process_id}) at batch {$batch_number}, stopping to prevent progress conflicts", 'warning' );
            return; // Another process owns the lock, stop this one immediately
        }
        
        // Also check if lock still exists (might have been released)
        $current_lock = get_option( 'yatco_import_lock', false );
        if ( $current_lock === false ) {
            yatco_log( "Full Import: Import lock was released at batch {$batch_number}, stopping", 'warning' );
            return; // Lock was released (probably by stop button), stop this import
        }
        
        // Reset execution time limit periodically to prevent timeout
        @set_time_limit( 300 ); // Reset to 5 minutes for each batch
        
        yatco_log( "Full Import: Starting batch {$batch_number} (" . count( $batch ) . " vessels)", 'info' );
        
            // Check stop flag (check both option and transient) - DON'T DELETE IT, keep it so it persists
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        }
        if ( $stop_flag !== false ) {
                yatco_log( '🛑 Full Import: Stop flag detected in batch, cancelling immediately', 'warning' );
            yatco_clear_import_status( 'full' );
            yatco_update_import_status_message( 'Full Import stopped by user.' );
                // DON'T delete stop flag - keep it so it can be checked again if import continues
            return;
        }
        
        foreach ( $batch as $vessel_id ) {
            // Check stop flag (check both option and transient) - DON'T DELETE IT
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
                $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Full Import: Stop flag detected before vessel import, cancelling immediately', 'warning' );
                yatco_clear_import_status( 'full' );
                yatco_update_import_status_message( 'Full Import stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
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
                        yatco_log( '🛑 Full Import: Stop flag detected during delay, cancelling immediately', 'warning' );
                        yatco_clear_import_status( 'full' );
                        yatco_update_import_status_message( 'Full Import stopped by user.', 60 );
                        // DON'T delete stop flag - keep it so it can be checked again
                        return;
                    }
                }
            }
            
            // Import full vessel data
            // CRITICAL: Check stop flag ONE MORE TIME right before starting import (most important check)
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
                $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Full Import: Stop flag detected right before vessel import, cancelling immediately', 'warning' );
                yatco_clear_import_status( 'full' );
                yatco_update_import_status_message( 'Full Import stopped by user.', 60 );
                delete_option( 'yatco_import_lock' );
                delete_option( 'yatco_import_process_id' );
                return;
            }
            
            // Increment attempted counter BEFORE processing (shows progress even if import fails)
            $attempted++;
            $vessel_position = $resume_from_index + $attempted;
            // Try to get vessel name from existing post if available (for better log visibility)
            $vessel_name_display = '';
            if ( is_array( $vessel_id_lookup ) && isset( $vessel_id_lookup[ intval( $vessel_id ) ] ) ) {
                $existing_post_id = $vessel_id_lookup[ intval( $vessel_id ) ];
                $post_title = get_the_title( intval( $existing_post_id ) );
                if ( ! empty( $post_title ) && $post_title !== 'Auto Draft' && strpos( $post_title, 'Yacht ' ) !== 0 ) {
                    $vessel_name_display = " ({$post_title})";
                }
            }
            yatco_log( "Full Import: Starting vessel {$vessel_id}{$vessel_name_display} (position {$vessel_position} of {$total_to_process}, {$processed} successful so far)", 'info' );
            
            // Log memory usage every 10 vessels (use attempted instead of processed so it logs even if vessels fail)
            if ( $attempted % 10 == 0 && $attempted > 0 && function_exists( 'memory_get_usage' ) ) {
                $memory_mb = round( memory_get_usage( true ) / 1024 / 1024, 2 );
                $peak_memory_mb = round( memory_get_peak_usage( true ) / 1024 / 1024, 2 );
                yatco_log( "Full Import: Memory usage - Current: {$memory_mb} MB, Peak: {$peak_memory_mb} MB (after {$attempted} attempts, {$processed} successful)", 'info' );
            }
            
            // Reset execution time before each vessel import
            @set_time_limit( 120 ); // 2 minutes per vessel max (reduced from 3)
            
            // Track start time for timeout detection
            $vessel_start_time = time();
            $vessel_max_time = 120; // Maximum seconds per vessel (2 minutes)
            
            // Wrap in try-catch to handle errors gracefully and continue
            try {
                $import_result = yatco_import_single_vessel( $token, $vessel_id, $vessel_id_lookup, $mlsid_lookup );
                
                // Check if vessel import took too long
                $vessel_elapsed = time() - $vessel_start_time;
                if ( $vessel_elapsed > $vessel_max_time ) {
                    yatco_log( "Full Import: Vessel {$vessel_id} took {$vessel_elapsed} seconds (exceeded {$vessel_max_time}s limit), but completed", 'warning' );
                }
                
                // Check if connection was aborted (browser closed, timeout, etc.)
                if ( connection_aborted() ) {
                    yatco_log( "Full Import: Connection aborted while processing vessel {$vessel_id}. Saving progress...", 'warning' );
                    // Save progress before returning (using current attempted count for position)
                    $current_position = $resume_from_index + $attempted;
                    $percent = $total_to_process > 0 ? round( ( $attempted / $total_to_process ) * 100, 1 ) : 0;
                    $pending = $total_to_process - $attempted;
                    $progress_data = array(
                        'last_processed'   => $current_position, // Position based on attempted
                        'processed'        => $processed,        // Actual count of successfully processed vessels
                        'failed'           => $failed,           // Count of failed vessel imports
                        'attempted'        => $attempted,        // Count of vessels attempted
                        'pending'          => $pending,          // Count of vessels remaining to process
                        'total'            => $total_to_process, // Total vessels to process (new vessels only)
                        'total_from_api'   => $total_from_api,   // Total vessels from API (before filtering)
                        'already_imported' => $already_imported_count, // Vessels that were already imported
                        'timestamp'        => time(),
                        'percent'          => $percent,
                        'last_vessel_id'   => $vessel_id,        // Save last vessel ID for debugging
                    );
                    // Use wp_options for more reliable progress storage
                    yatco_update_import_status( $progress_data, 'full' );
                    yatco_update_import_status_message( "Full Import: Connection lost. Attempted {$attempted} of {$total_to_process} vessels ({$percent}%), {$processed} successful. Progress saved - auto-resume enabled." );
                    yatco_log( "Full Import: Progress saved before connection abort: position {$current_position}, attempted {$attempted}, processed {$processed}, last vessel {$vessel_id}", 'info' );
                    update_option( 'yatco_import_auto_resume', time(), false ); // Enable auto-resume
                    return;
                }
            } catch ( Exception $e ) {
                yatco_log( "Full Import: Exception while importing vessel {$vessel_id}: " . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), 'error' );
                $import_result = new WP_Error( 'import_exception', $e->getMessage() );
                
                // Save progress even on error so we can resume
                $current_position = $resume_from_index + $attempted;
                $pending = $total_to_process - $attempted;
                $percent = $total_to_process > 0 ? round( ( $attempted / $total_to_process ) * 100, 1 ) : 0;
                $progress_data = array(
                    'last_processed'   => $current_position, // Position based on attempted
                    'processed'        => $processed,        // Actual count of successfully processed vessels
                    'failed'           => $failed,           // Count of failed vessel imports
                    'attempted'        => $attempted,        // Count of vessels attempted
                    'pending'          => $pending,          // Count of vessels remaining to process
                    'total'            => $total_to_process, // Total vessels to process (new vessels only)
                    'total_from_api'   => $total_from_api,   // Total vessels from API (before filtering)
                    'already_imported' => $already_imported_count, // Vessels that were already imported
                    'timestamp'        => time(),
                    'percent'          => $percent,
                );
                // Use wp_options for more reliable progress storage
                yatco_update_import_status( $progress_data, 'full' );
            } catch ( Error $e ) {
                // PHP 7+ Error class (fatal errors that can be caught)
                yatco_log( "Full Import: Fatal error while importing vessel {$vessel_id}: " . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), 'error' );
                $import_result = new WP_Error( 'import_fatal_error', $e->getMessage() );
                
                // Save progress even on fatal error so we can resume
                $current_position = $resume_from_index + $attempted;
                $pending = $total_to_process - $attempted;
                $percent = $total_to_process > 0 ? round( ( $attempted / $total_to_process ) * 100, 1 ) : 0;
                $progress_data = array(
                    'last_processed'   => $current_position, // Position based on attempted
                    'processed'        => $processed,        // Actual count of successfully processed vessels
                    'failed'           => $failed,           // Count of failed vessel imports
                    'attempted'        => $attempted,        // Count of vessels attempted
                    'pending'          => $pending,          // Count of vessels remaining to process
                    'total'            => $total_to_process, // Total vessels to process (new vessels only)
                    'total_from_api'   => $total_from_api,   // Total vessels from API (before filtering)
                    'already_imported' => $already_imported_count, // Vessels that were already imported
                    'timestamp'        => time(),
                    'percent'          => $percent,
                );
                // Use wp_options for more reliable progress storage
                yatco_update_import_status( $progress_data, 'full' );
            }
            
            // Check if vessel import is taking too long and skip if needed
            $vessel_elapsed = time() - $vessel_start_time;
            if ( $vessel_elapsed > $vessel_max_time && ( is_wp_error( $import_result ) || $import_result === null ) ) {
                yatco_log( "Full Import: Vessel {$vessel_id} exceeded time limit ({$vessel_elapsed}s > {$vessel_max_time}s), skipping to next vessel", 'warning' );
                $import_result = new WP_Error( 'import_timeout', "Vessel import exceeded {$vessel_max_time} second timeout" );
                
                // Save progress after timeout (use attempted for position)
                $current_position = $resume_from_index + $attempted;
                $pending = $total_to_process - $attempted;
                $percent = $total_to_process > 0 ? round( ( $attempted / $total_to_process ) * 100, 1 ) : 0;
                $progress_data = array(
                    'last_processed'   => $current_position, // Position based on attempted
                    'processed'        => $processed,        // Actual count of successfully processed vessels
                    'failed'           => $failed,           // Count of failed vessel imports
                    'attempted'        => $attempted,        // Count of vessels attempted
                    'pending'          => $pending,          // Count of vessels remaining to process
                    'total'            => $total_to_process, // Total vessels to process (new vessels only)
                    'total_from_api'   => $total_from_api,   // Total vessels from API (before filtering)
                    'already_imported' => $already_imported_count, // Vessels that were already imported
                    'timestamp'        => time(),
                    'percent'          => $percent,
                );
                // Use wp_options for more reliable progress storage
                yatco_update_import_status( $progress_data, 'full' );
            }
            
            // Check stop flag after import (check both option and transient) - DON'T DELETE IT
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
                $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Full Import: Stop flag detected after vessel import, cancelling immediately', 'warning' );
                yatco_clear_import_status( 'full' );
                yatco_update_import_status_message( 'Full Import stopped by user.' );
                // DON'T delete stop flag - keep it so it can be checked again
                return;
            }
            
            // Flush output after each vessel (when running directly)
            if ( ob_get_level() > 0 ) {
                ob_flush();
            }
            flush();
            
            if ( ! is_wp_error( $import_result ) ) {
                $processed++;
                // Try to get vessel name from post title if available
                $vessel_name_display = '';
                if ( is_numeric( $import_result ) ) {
                    $post_title = get_the_title( intval( $import_result ) );
                    if ( ! empty( $post_title ) && $post_title !== 'Auto Draft' && strpos( $post_title, 'Yacht ' ) !== 0 ) {
                        $vessel_name_display = " ({$post_title})";
                    }
                }
                yatco_log( "Full Import: Successfully imported vessel {$vessel_id}{$vessel_name_display}", 'debug' );
            } elseif ( $import_result->get_error_code() === 'import_stopped' ) {
                yatco_log( '🛑 Full Import: Import stopped during vessel processing (stop flag detected in import function)', 'warning' );
                yatco_clear_import_status( 'full' );
                yatco_update_import_status_message( 'Full Import stopped by user.' );
                // DON'T delete stop flag here - it's already been handled in the import function
                return;
            } else {
                $failed++;
                // Try to get vessel name from existing post if available
                $vessel_name_display = '';
                $error_message = $import_result->get_error_message();
                // Check if we can get name from existing post (fast lookup using vessel_id_lookup if available)
                if ( is_array( $vessel_id_lookup ) && isset( $vessel_id_lookup[ intval( $vessel_id ) ] ) ) {
                    $existing_post_id = $vessel_id_lookup[ intval( $vessel_id ) ];
                    $post_title = get_the_title( intval( $existing_post_id ) );
                    if ( ! empty( $post_title ) && $post_title !== 'Auto Draft' && strpos( $post_title, 'Yacht ' ) !== 0 ) {
                        $vessel_name_display = " ({$post_title})";
                    }
                } else {
                    // Fallback: query for existing post (slower)
                    $existing_posts = get_posts( array(
                        'post_type' => 'yacht',
                        'meta_key' => 'yacht_vessel_id',
                        'meta_value' => $vessel_id,
                        'numberposts' => 1,
                        'fields' => 'ids',
                    ) );
                    if ( ! empty( $existing_posts ) ) {
                        $post_title = get_the_title( intval( $existing_posts[0] ) );
                        if ( ! empty( $post_title ) && $post_title !== 'Auto Draft' && strpos( $post_title, 'Yacht ' ) !== 0 ) {
                            $vessel_name_display = " ({$post_title})";
                        }
                    }
                }
                yatco_log( "Full Import: Error importing vessel {$vessel_id}{$vessel_name_display}: {$error_message}", 'error' );
            }
            
            // Save progress after EACH vessel for real-time updates (critical for progress bar)
            // Track position (for resuming) and all counts for comprehensive progress display
            $current_position = $resume_from_index + $attempted; // Use attempted for position (shows actual progress through list)
            $pending = $total_to_process - $attempted; // Vessels remaining to process
            $percent = $total_to_process > 0 ? round( ( $attempted / $total_to_process ) * 100, 1 ) : 0; // Use attempted for percent (shows progress through list)
            $progress_data = array(
                'last_processed'   => $current_position, // Position in array (for resuming) - based on attempted
                'processed'        => $processed,        // Count of successfully processed vessels
                'failed'           => $failed,           // Count of failed vessel imports
                'attempted'        => $attempted,        // Count of vessels attempted
                'pending'          => $pending,          // Count of vessels remaining to process
                'total'            => $total_to_process, // Total vessels to process (new vessels only)
                'total_from_api'   => $total_from_api,   // Total vessels from API (before filtering)
                'already_imported' => $already_imported_count, // Vessels that were already imported
                'timestamp'        => time(),
                'percent'          => $percent,
                'last_vessel_id'   => $vessel_id,        // Track last vessel processed for debugging
            );
            
            // Check if this process still owns the lock (prevent overwriting progress from other processes)
            $lock_process_id = get_option( 'yatco_import_process_id', false );
            // Use loose comparison to handle string vs int PID differences
            if ( $lock_process_id !== false && strval( $lock_process_id ) !== strval( $process_id ) ) {
                yatco_log( "Full Import: Lock owned by different process ({$lock_process_id} vs {$process_id}), stopping to prevent progress conflicts", 'warning' );
                delete_option( 'yatco_import_lock' ); // Release lock since we're stopping
                delete_option( 'yatco_import_process_id' );
                return; // Another process owns the lock, stop this one
            }
            
            // Update import lock timestamp to show import is still active
            update_option( 'yatco_import_lock', time(), false );
            
            // Save progress to wp_options (more reliable than transients)
            require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
            yatco_update_import_status( $progress_data, 'full' );
            
            // Save status message to wp_options
            $status_message = "Full Import: {$total_from_api} total from API | {$already_imported_count} already imported | {$processed} successful | {$failed} failed | {$pending} pending";
            yatco_update_import_status_message( $status_message );
            
            // Log progress save for debugging (use attempted so it logs even if all vessels fail)
            if ( $attempted % 10 == 0 || $attempted <= 5 ) {
                yatco_log( "Full Import: Progress saved - {$total_from_api} total from API | {$already_imported_count} already imported | {$processed} successful | {$failed} failed | {$pending} pending - Last vessel: {$vessel_id}", 'info' );
            }
            
            // Memory cleanup after each vessel to prevent memory buildup
            if ( function_exists( 'gc_collect_cycles' ) ) {
                gc_collect_cycles();
            }
        }
        
        // Progress is already saved after each vessel, but save again after batch for extra safety
        // Track position in the filtered array (vessels to process) - use attempted for consistency
        $current_position = $resume_from_index + $attempted;
        $pending = $total_to_process - $attempted;
        $percent = $total_to_process > 0 ? round( ( $attempted / $total_to_process ) * 100, 1 ) : 0;
        $progress_data = array(
            'last_processed'   => $current_position, // Position in filtered array - based on attempted
            'processed'        => $processed,        // Actual count of successfully processed vessels
            'failed'           => $failed,           // Count of failed vessel imports
            'attempted'        => $attempted,        // Count of vessels attempted
            'pending'          => $pending,          // Count of vessels remaining to process
            'total'            => $total_to_process, // Total vessels to process (new vessels only)
            'total_from_api'   => $total_from_api,   // Total vessels from API (before filtering)
            'already_imported' => $already_imported_count, // Vessels that were already imported
            'timestamp'        => time(),
            'percent'          => $percent,
        );
        // Use wp_options for more reliable progress storage
        require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
        yatco_update_import_status( $progress_data, 'full' );
        $status_message = "Full Import: {$total_from_api} total from API | {$already_imported_count} already imported | {$processed} successful | {$failed} failed | {$pending} pending";
        yatco_update_import_status_message( $status_message );
        
        yatco_log( "Full Import: Batch {$batch_number} complete - {$total_from_api} total from API | {$already_imported_count} already imported | {$processed} successful | {$failed} failed | {$pending} pending | Position: {$current_position}", 'info' );
        
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
        if ( $attempted < $total_to_process ) {
            // Break delay into 1-second chunks and check stop flag between chunks
            for ( $i = 0; $i < $delay_seconds; $i++ ) {
                sleep( 1 );
                // Check stop flag every second (check both option and transient)
                $stop_flag = get_option( 'yatco_import_stop_flag', false );
                if ( $stop_flag === false ) {
                    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
                }
                if ( $stop_flag !== false ) {
                    yatco_log( '🛑 Full Import: Stop flag detected during batch delay, cancelling', 'warning' );
                    delete_transient( 'yatco_import_progress' );
                    wp_cache_delete( 'yatco_import_progress', 'transient' );
                    delete_option( 'yatco_import_lock' ); // Release lock
                    delete_option( 'yatco_import_process_id' ); // Release process ID
                    yatco_update_import_status_message( 'Full Import stopped by user.' );
                    // DON'T delete stop flag here - it's handled by the stop handler
                    return;
                }
            }
        }
    }
    
    // Check if we've completed all vessels (use attempted, not processed, since we need to process all even if some fail)
    if ( $attempted >= $total_to_process ) {
        // Clear progress and stop flags (import completed successfully)
        yatco_clear_import_status( 'full' );
        delete_option( 'yatco_import_stop_flag' );
        delete_transient( 'yatco_cache_warming_stop' );
        delete_option( 'yatco_import_auto_resume' );
        $failed_count = $attempted - $processed;
        $success_rate = $attempted > 0 ? round( ( $processed / $attempted ) * 100, 1 ) : 0;
        $completion_message = "Full Import Complete: {$total_from_api} total from API | {$already_imported_count} already imported | {$processed} successful ({$success_rate}%) | {$failed_count} failed";
        yatco_update_import_status_message( $completion_message );
        yatco_log( $completion_message, 'info' );
        
        // Log final memory usage for debugging
        if ( function_exists( 'memory_get_peak_usage' ) ) {
            $peak_memory = round( memory_get_peak_usage( true ) / 1024 / 1024, 2 );
            yatco_log( "Full Import: Peak memory usage: {$peak_memory} MB", 'info' );
        }
        
        // Release import lock and process ID
        delete_option( 'yatco_import_lock' );
        delete_option( 'yatco_import_process_id' );
        yatco_log( "Full Import: Import lock released (Process ID: {$process_id})", 'info' );
    } else {
        // Import incomplete - save progress and set auto-resume flag
        // NOTE: We do NOT release the lock here - keep it so auto-resume can continue this same import
        // The lock will be released when import completes or is stopped
        $last_vessel_id = isset( $progress_data['last_vessel_id'] ) ? $progress_data['last_vessel_id'] : 'unknown';
        $attempted_final = isset( $progress_data['attempted'] ) ? intval( $progress_data['attempted'] ) : $processed;
        $processed_final = isset( $progress_data['processed'] ) ? intval( $progress_data['processed'] ) : 0;
        $failed_final = isset( $progress_data['failed'] ) ? intval( $progress_data['failed'] ) : 0;
        $pending_final = isset( $progress_data['pending'] ) ? intval( $progress_data['pending'] ) : 0;
        yatco_log( "Full Import: INCOMPLETE - Stopped at {$attempted_final}/{$total_to_process} attempted, {$processed_final} successful, {$failed_final} failed, {$pending_final} pending (last vessel: {$last_vessel_id}). Auto-resume enabled. Lock maintained for resume.", 'warning' );
        
        // Log system information for debugging
        if ( function_exists( 'memory_get_peak_usage' ) ) {
            $peak_memory_mb = round( memory_get_peak_usage( true ) / 1024 / 1024, 2 );
            $memory_limit = ini_get( 'memory_limit' );
            yatco_log( "Full Import: Memory at stop - Peak: {$peak_memory_mb}MB, Limit: {$memory_limit}", 'info' );
        }
        
        $max_exec_time = ini_get( 'max_execution_time' );
        if ( $max_exec_time > 0 ) {
            yatco_log( "Full Import: Execution time limit: {$max_exec_time} seconds", 'info' );
        }
        
        update_option( 'yatco_import_auto_resume', time(), false );
        yatco_update_import_status_message( "Full Import paused: Processed {$processed} of {$total_to_process} vessels (last: {$last_vessel_id}). Auto-resuming..." );
    }
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
    
    $total_from_api = count( $current_ids );
    yatco_log( "Daily Sync: Found {$total_from_api} total vessel IDs from API", 'info' );
    
    // Build lookup map for already imported vessels (compare against database)
    set_transient( 'yatco_cache_warming_status', 'Daily Sync: Checking for already imported vessels...', 600 );
    global $wpdb;
    $imported_vessel_id_lookup = array();
    
    // Get all existing yacht posts with their vessel IDs
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
            $imported_vessel_id_lookup[ intval( $post->vessel_id ) ] = intval( $post->post_id );
        }
    }
    
    $already_imported_count = count( $imported_vessel_id_lookup );
    yatco_log( "Daily Sync: Found {$already_imported_count} vessels already imported in database", 'info' );
    
    // Filter out vessels that are already imported
    $current_ids_int = array_map( 'intval', $current_ids );
    $imported_ids = array_keys( $imported_vessel_id_lookup );
    $vessels_not_imported = array_diff( $current_ids_int, $imported_ids );
    
    yatco_log( "Daily Sync: {$total_from_api} total from API, {$already_imported_count} already imported, " . count( $vessels_not_imported ) . " vessels need importing", 'info' );
    
    // Get stored vessel IDs (for tracking new/removed based on API list)
    $stored_ids = get_option( 'yatco_vessel_ids', array() );
    
    // Find removed vessels (vessels that were in stored_ids but not in current_ids)
    $removed_ids = array_diff( $stored_ids, $current_ids );
    
    // Find new vessels that need importing:
    // Vessels that are in current_ids but NOT in stored_ids AND NOT already imported
    $new_in_api = array_diff( $current_ids_int, array_map( 'intval', $stored_ids ) );
    $new_ids = array_intersect( $new_in_api, $vessels_not_imported );
    
    yatco_log( "Daily Sync: Found " . count( $new_ids ) . " new vessels to import (not in stored list and not already imported)", 'info' );
    
    // Find existing vessels (for price/days on market updates) - these are vessels that are both in current_ids and already imported
    $existing_ids = array_intersect( $current_ids_int, $imported_ids );
    
    // Update stored IDs
    update_option( 'yatco_vessel_ids', $current_ids, false );
    
    set_transient( 'yatco_cache_warming_status', "Daily Sync: {$total_from_api} total from API, {$already_imported_count} already imported, " . count( $new_ids ) . " new to import...", 600 );
    
    $total_steps = count( $removed_ids ) + count( $new_ids ) + count( $existing_ids );
    $sync_progress['total'] = $total_steps;
    $sync_progress['step'] = 'processing';
    set_transient( 'yatco_daily_sync_progress', $sync_progress, 3600 );
    set_transient( 'yatco_cache_warming_status', "Daily Sync: Processing {$total_steps} vessels...", 600 );
    
    // Mark removed vessels as draft
    $removed_count = 0;
    $processed = 0;
    foreach ( $removed_ids as $vessel_id ) {
        // Check stop flag - check both option and transient
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        }
        if ( $stop_flag !== false ) {
            yatco_log( '🛑 Daily Sync: Stop flag detected, cancelling', 'warning' );
            delete_transient( 'yatco_daily_sync_progress' );
            set_transient( 'yatco_cache_warming_status', 'Daily Sync stopped by user.', 60 );
            // DON'T delete stop flag - keep it so it can be checked again
            return;
        }
        
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
            // Check stop flag - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
                $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Daily Sync: Stop flag detected, cancelling', 'warning' );
                delete_transient( 'yatco_daily_sync_progress' );
                set_transient( 'yatco_cache_warming_status', 'Daily Sync stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
                return;
            }
            
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
            // Check stop flag - check both option and transient
            $stop_flag = get_option( 'yatco_import_stop_flag', false );
            if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
            }
            if ( $stop_flag !== false ) {
                yatco_log( '🛑 Daily Sync: Stop flag detected in batch, cancelling', 'warning' );
                delete_transient( 'yatco_daily_sync_progress' );
                set_transient( 'yatco_cache_warming_status', 'Daily Sync stopped by user.', 60 );
                // DON'T delete stop flag - keep it so it can be checked again
                return;
            }
            
            foreach ( $batch as $vessel_id ) {
                // Check stop flag for each vessel - check both option and transient
                $stop_flag = get_option( 'yatco_import_stop_flag', false );
                if ( $stop_flag === false ) {
                    $stop_flag = get_transient( 'yatco_cache_warming_stop' );
                }
                if ( $stop_flag !== false ) {
                    yatco_log( '🛑 Daily Sync: Stop flag detected, cancelling', 'warning' );
                    delete_transient( 'yatco_daily_sync_progress' );
                    set_transient( 'yatco_cache_warming_status', 'Daily Sync stopped by user.', 60 );
                    // DON'T delete stop flag - keep it so it can be checked again
                    return;
                }
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
        'date' => date( 'Y-m-d', time() ),
    );
    
    // Store in history (keep last 90 days)
    $history = get_option( 'yatco_daily_sync_history', array() );
    if ( ! is_array( $history ) ) {
        $history = array();
    }
    
    // Add today's results
    $today = date( 'Y-m-d', time() );
    $history[ $today ] = $sync_results;
    
    // Keep only last 90 days
    ksort( $history );
    if ( count( $history ) > 90 ) {
        $history = array_slice( $history, -90, null, true );
    }
    
    update_option( 'yatco_daily_sync_history', $history, false );
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

