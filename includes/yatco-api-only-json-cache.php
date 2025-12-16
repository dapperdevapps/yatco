<?php
/**
 * API-Only Mode with JSON Cache Storage
 * 
 * This module provides a JSON-based caching approach that:
 * 1. Stores lightweight vessel data as JSON in WordPress options
 * 2. Allows fast queries without API calls
 * 3. Fetches full details from API only when needed
 * 4. Easy to manage and clear
 * 
 * Storage: WordPress options (JSON format)
 * Performance: Fast queries + on-demand full details
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the cache option name.
 * 
 * @return string Option name
 */
function yatco_json_cache_get_option_name() {
    return 'yatco_vessels_json_cache';
}

/**
 * Get all cached vessels as array.
 * 
 * @return array Array of vessel data arrays, keyed by vessel_id
 */
function yatco_json_cache_get_all() {
    $cache_data = get_option( yatco_json_cache_get_option_name(), array() );
    
    if ( is_string( $cache_data ) ) {
        // Legacy: if stored as JSON string, decode it
        $cache_data = json_decode( $cache_data, true );
        if ( ! is_array( $cache_data ) ) {
            $cache_data = array();
        }
    }
    
    return $cache_data;
}

/**
 * Store vessel data in JSON cache.
 * 
 * @param array $vessel_data Vessel data array (from yatco_api_only_build_vessel_data)
 * @return bool True on success
 */
function yatco_json_cache_store_vessel( $vessel_data ) {
    if ( empty( $vessel_data['vessel_id'] ) ) {
        return false;
    }
    
    $cache = yatco_json_cache_get_all();
    $vessel_id = intval( $vessel_data['vessel_id'] );
    
    // Store only lightweight data (what we need for listings)
    $lightweight_data = array(
        'vessel_id' => $vessel_id,
        'mlsid' => isset( $vessel_data['mlsid'] ) ? $vessel_data['mlsid'] : '',
        'name' => isset( $vessel_data['name'] ) ? $vessel_data['name'] : '',
        'price_usd' => isset( $vessel_data['price_usd'] ) ? $vessel_data['price_usd'] : null,
        'price_eur' => isset( $vessel_data['price_eur'] ) ? $vessel_data['price_eur'] : null,
        'price_formatted' => isset( $vessel_data['price_formatted'] ) ? $vessel_data['price_formatted'] : '',
        'year' => isset( $vessel_data['year'] ) ? $vessel_data['year'] : '',
        'loa_feet' => isset( $vessel_data['loa_feet'] ) ? $vessel_data['loa_feet'] : null,
        'loa_meters' => isset( $vessel_data['loa_meters'] ) ? $vessel_data['loa_meters'] : null,
        'builder' => isset( $vessel_data['builder'] ) ? $vessel_data['builder'] : '',
        'category' => isset( $vessel_data['category'] ) ? $vessel_data['category'] : '',
        'sub_category' => isset( $vessel_data['sub_category'] ) ? $vessel_data['sub_category'] : '',
        'type' => isset( $vessel_data['type'] ) ? $vessel_data['type'] : '',
        'condition' => isset( $vessel_data['condition'] ) ? $vessel_data['condition'] : '',
        'location' => isset( $vessel_data['location'] ) ? $vessel_data['location'] : '',
        'location_city' => isset( $vessel_data['location_city'] ) ? $vessel_data['location_city'] : '',
        'location_state' => isset( $vessel_data['location_state'] ) ? $vessel_data['location_state'] : '',
        'location_country' => isset( $vessel_data['location_country'] ) ? $vessel_data['location_country'] : '',
        'state_rooms' => isset( $vessel_data['state_rooms'] ) ? $vessel_data['state_rooms'] : 0,
        'image_url' => isset( $vessel_data['image_url'] ) ? $vessel_data['image_url'] : '',
        'yatco_listing_url' => isset( $vessel_data['yatco_listing_url'] ) ? $vessel_data['yatco_listing_url'] : '',
        'last_updated' => time(), // Track when this was cached
    );
    
    $cache[ $vessel_id ] = $lightweight_data;
    
    // Update option (WordPress handles serialization automatically)
    return update_option( yatco_json_cache_get_option_name(), $cache );
}

/**
 * Get a single vessel from cache.
 * 
 * @param int $vessel_id Vessel ID
 * @return array|null Vessel data array or null if not found
 */
function yatco_json_cache_get_vessel( $vessel_id ) {
    $cache = yatco_json_cache_get_all();
    $vessel_id = intval( $vessel_id );
    
    return isset( $cache[ $vessel_id ] ) ? $cache[ $vessel_id ] : null;
}

/**
 * Get vessels from cache with filtering.
 * 
 * @param array $filters Filter criteria
 * @param int   $limit Maximum number of results
 * @return array Array of vessel data arrays
 */
function yatco_json_cache_get_vessels( $filters = array(), $limit = 50 ) {
    $cache = yatco_json_cache_get_all();
    
    if ( empty( $cache ) ) {
        return array();
    }
    
    $results = array();
    
    foreach ( $cache as $vessel_id => $vessel ) {
        // Apply filters
        if ( ! empty( $filters['price_min'] ) && ( empty( $vessel['price_usd'] ) || $vessel['price_usd'] < floatval( $filters['price_min'] ) ) ) {
            continue;
        }
        if ( ! empty( $filters['price_max'] ) && ( empty( $vessel['price_usd'] ) || $vessel['price_usd'] > floatval( $filters['price_max'] ) ) ) {
            continue;
        }
        if ( ! empty( $filters['year_min'] ) && ( empty( $vessel['year'] ) || intval( $vessel['year'] ) < intval( $filters['year_min'] ) ) ) {
            continue;
        }
        if ( ! empty( $filters['year_max'] ) && ( empty( $vessel['year'] ) || intval( $vessel['year'] ) > intval( $filters['year_max'] ) ) ) {
            continue;
        }
        if ( ! empty( $filters['loa_min'] ) && ( empty( $vessel['loa_feet'] ) || $vessel['loa_feet'] < floatval( $filters['loa_min'] ) ) ) {
            continue;
        }
        if ( ! empty( $filters['loa_max'] ) && ( empty( $vessel['loa_feet'] ) || $vessel['loa_feet'] > floatval( $filters['loa_max'] ) ) ) {
            continue;
        }
        if ( ! empty( $filters['builder'] ) && $vessel['builder'] !== $filters['builder'] ) {
            continue;
        }
        if ( ! empty( $filters['category'] ) && $vessel['category'] !== $filters['category'] ) {
            continue;
        }
        if ( ! empty( $filters['type'] ) && $vessel['type'] !== $filters['type'] ) {
            continue;
        }
        if ( ! empty( $filters['condition'] ) && $vessel['condition'] !== $filters['condition'] ) {
            continue;
        }
        
        // Convert to display format
        $results[] = array(
            'id' => $vessel['vessel_id'],
            'post_id' => 0,
            'name' => $vessel['name'],
            'price' => $vessel['price_formatted'] ? $vessel['price_formatted'] : ( $vessel['price_usd'] ? '$' . number_format( $vessel['price_usd'] ) : '' ),
            'price_usd' => $vessel['price_usd'],
            'price_eur' => $vessel['price_eur'],
            'year' => $vessel['year'],
            'loa' => $vessel['loa_feet'] ? $vessel['loa_feet'] . ' ft' : '',
            'loa_feet' => $vessel['loa_feet'],
            'loa_meters' => $vessel['loa_meters'],
            'builder' => $vessel['builder'],
            'category' => $vessel['category'],
            'type' => $vessel['type'],
            'condition' => $vessel['condition'],
            'state_rooms' => $vessel['state_rooms'],
            'location' => $vessel['location'],
            'image' => $vessel['image_url'],
            'link' => $vessel['yatco_listing_url'],
        );
        
        // Stop if we've reached the limit
        if ( count( $results ) >= $limit ) {
            break;
        }
    }
    
    return $results;
}

/**
 * Get unique values for filter dropdowns from cache.
 * 
 * @return array Array with 'builders', 'categories', 'types', 'conditions' keys
 */
function yatco_json_cache_get_filter_values() {
    $cache = yatco_json_cache_get_all();
    
    $builders = array();
    $categories = array();
    $types = array();
    $conditions = array();
    
    foreach ( $cache as $vessel ) {
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
    
    return array(
        'builders' => $builders,
        'categories' => $categories,
        'types' => $types,
        'conditions' => $conditions,
    );
}

/**
 * Sync JSON cache with API.
 * Updates cache for all active vessels.
 * 
 * @param string $token API token
 * @param int    $batch_size Number of vessels to process per batch
 * @return array|WP_Error Array with 'processed', 'updated', 'errors', 'total' keys
 */
function yatco_json_cache_sync( $token, $batch_size = 50 ) {
    // Get all active vessel IDs
    if ( ! function_exists( 'yatco_api_only_get_vessel_ids' ) ) {
        return new WP_Error( 'yatco_function_missing', 'yatco_api_only_get_vessel_ids function not available' );
    }
    
    $vessel_ids = yatco_api_only_get_vessel_ids( $token );
    
    if ( is_wp_error( $vessel_ids ) ) {
        return $vessel_ids;
    }
    
    $processed = 0;
    $updated = 0;
    $errors = 0;
    $start_time = time();
    $max_time = 300; // 5 minutes max
    
    // Process in batches
    $batches = array_chunk( $vessel_ids, $batch_size );
    
    foreach ( $batches as $batch ) {
        // Check timeout
        if ( ( time() - $start_time ) > $max_time ) {
            break;
        }
        
        foreach ( $batch as $vessel_id ) {
            $processed++;
            
            // Get vessel data from API (cached)
            if ( ! function_exists( 'yatco_api_only_get_vessel_data' ) ) {
                $errors++;
                continue;
            }
            
            $vessel_data = yatco_api_only_get_vessel_data( $token, $vessel_id );
            
            if ( is_wp_error( $vessel_data ) ) {
                $errors++;
                continue;
            }
            
            // Store in JSON cache
            $result = yatco_json_cache_store_vessel( $vessel_data );
            
            if ( $result ) {
                $updated++;
            } else {
                $errors++;
            }
        }
    }
    
    return array(
        'processed' => $processed,
        'updated' => $updated,
        'errors' => $errors,
        'total' => count( $vessel_ids ),
    );
}

/**
 * Clear all JSON cache data.
 * 
 * @return bool True on success
 */
function yatco_json_cache_clear() {
    return delete_option( yatco_json_cache_get_option_name() );
}

/**
 * Get cache statistics.
 * 
 * @return array Array with 'total_vessels', 'cache_size', 'last_updated' keys
 */
function yatco_json_cache_get_stats() {
    $cache = yatco_json_cache_get_all();
    $total = count( $cache );
    
    // Estimate size (rough calculation)
    $cache_size = strlen( serialize( $cache ) );
    $cache_size_mb = round( $cache_size / 1024 / 1024, 2 );
    
    // Get oldest and newest update times
    $last_updated = 0;
    foreach ( $cache as $vessel ) {
        if ( isset( $vessel['last_updated'] ) && $vessel['last_updated'] > $last_updated ) {
            $last_updated = $vessel['last_updated'];
        }
    }
    
    return array(
        'total_vessels' => $total,
        'cache_size_bytes' => $cache_size,
        'cache_size_mb' => $cache_size_mb,
        'last_updated' => $last_updated,
        'last_updated_human' => $last_updated ? human_time_diff( $last_updated, time() ) . ' ago' : 'Never',
    );
}

