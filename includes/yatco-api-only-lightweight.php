<?php
/**
 * API-Only Mode with Lightweight Storage
 * 
 * This module provides a hybrid approach that:
 * 1. Stores lightweight vessel metadata in WordPress (for fast queries)
 * 2. Fetches full vessel details from API only when needed
 * 3. Uses minimal storage (~1-2MB vs 10-20GB for full CPT)
 * 4. Allows fast filtering and searching without API calls
 * 
 * Storage: Lightweight custom table or minimal post meta
 * Performance: Fast queries + on-demand full details
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create lightweight storage table for vessel metadata.
 * This stores only essential fields needed for listing/filtering.
 */
function yatco_api_only_create_lightweight_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'yatco_vessels_lightweight';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        vessel_id bigint(20) NOT NULL,
        mlsid varchar(100) DEFAULT NULL,
        name varchar(255) DEFAULT NULL,
        price_usd decimal(15,2) DEFAULT NULL,
        price_eur decimal(15,2) DEFAULT NULL,
        year int(4) DEFAULT NULL,
        loa_feet decimal(10,2) DEFAULT NULL,
        loa_meters decimal(10,2) DEFAULT NULL,
        builder varchar(255) DEFAULT NULL,
        category varchar(255) DEFAULT NULL,
        sub_category varchar(255) DEFAULT NULL,
        type varchar(255) DEFAULT NULL,
        condition varchar(100) DEFAULT NULL,
        location varchar(255) DEFAULT NULL,
        location_city varchar(100) DEFAULT NULL,
        location_state varchar(100) DEFAULT NULL,
        location_country varchar(100) DEFAULT NULL,
        state_rooms int(3) DEFAULT NULL,
        image_url text DEFAULT NULL,
        yatco_listing_url text DEFAULT NULL,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY vessel_id (vessel_id),
        KEY mlsid (mlsid),
        KEY price_usd (price_usd),
        KEY year (year),
        KEY loa_feet (loa_feet),
        KEY builder (builder),
        KEY category (category),
        KEY type (type),
        KEY condition (condition)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

/**
 * Store lightweight vessel data.
 * 
 * @param array $vessel_data Vessel data array (from yatco_api_only_build_vessel_data)
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function yatco_api_only_store_lightweight( $vessel_data ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'yatco_vessels_lightweight';
    
    // Ensure table exists
    yatco_api_only_create_lightweight_table();
    
    $data = array(
        'vessel_id' => intval( $vessel_data['vessel_id'] ),
        'mlsid' => isset( $vessel_data['mlsid'] ) ? sanitize_text_field( $vessel_data['mlsid'] ) : '',
        'name' => isset( $vessel_data['name'] ) ? sanitize_text_field( $vessel_data['name'] ) : '',
        'price_usd' => isset( $vessel_data['price_usd'] ) && $vessel_data['price_usd'] > 0 ? floatval( $vessel_data['price_usd'] ) : null,
        'price_eur' => isset( $vessel_data['price_eur'] ) && $vessel_data['price_eur'] > 0 ? floatval( $vessel_data['price_eur'] ) : null,
        'year' => ! empty( $vessel_data['year'] ) ? intval( $vessel_data['year'] ) : null,
        'loa_feet' => isset( $vessel_data['loa_feet'] ) && $vessel_data['loa_feet'] > 0 ? floatval( $vessel_data['loa_feet'] ) : null,
        'loa_meters' => isset( $vessel_data['loa_meters'] ) && $vessel_data['loa_meters'] > 0 ? floatval( $vessel_data['loa_meters'] ) : null,
        'builder' => isset( $vessel_data['builder'] ) ? sanitize_text_field( $vessel_data['builder'] ) : '',
        'category' => isset( $vessel_data['category'] ) ? sanitize_text_field( $vessel_data['category'] ) : '',
        'sub_category' => isset( $vessel_data['sub_category'] ) ? sanitize_text_field( $vessel_data['sub_category'] ) : '',
        'type' => isset( $vessel_data['type'] ) ? sanitize_text_field( $vessel_data['type'] ) : '',
        'condition' => isset( $vessel_data['condition'] ) ? sanitize_text_field( $vessel_data['condition'] ) : '',
        'location' => isset( $vessel_data['location'] ) ? sanitize_text_field( $vessel_data['location'] ) : '',
        'location_city' => isset( $vessel_data['location_city'] ) ? sanitize_text_field( $vessel_data['location_city'] ) : '',
        'location_state' => isset( $vessel_data['location_state'] ) ? sanitize_text_field( $vessel_data['location_state'] ) : '',
        'location_country' => isset( $vessel_data['location_country'] ) ? sanitize_text_field( $vessel_data['location_country'] ) : '',
        'state_rooms' => isset( $vessel_data['state_rooms'] ) ? intval( $vessel_data['state_rooms'] ) : null,
        'image_url' => isset( $vessel_data['image_url'] ) ? esc_url_raw( $vessel_data['image_url'] ) : '',
        'yatco_listing_url' => isset( $vessel_data['yatco_listing_url'] ) ? esc_url_raw( $vessel_data['yatco_listing_url'] ) : '',
    );
    
    $result = $wpdb->replace( $table_name, $data );
    
    if ( $result === false ) {
        return new WP_Error( 'yatco_storage_error', 'Failed to store lightweight vessel data: ' . $wpdb->last_error );
    }
    
    return true;
}

/**
 * Get vessels from lightweight storage with filtering.
 * 
 * @param array $filters Filter criteria (price_min, price_max, year_min, year_max, loa_min, loa_max, etc.)
 * @param int   $limit Maximum number of results
 * @return array Array of vessel data arrays
 */
function yatco_api_only_get_lightweight_vessels( $filters = array(), $limit = 50 ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'yatco_vessels_lightweight';
    
    // Build WHERE clause
    $where = array( '1=1' );
    $where_values = array();
    
    if ( ! empty( $filters['price_min'] ) ) {
        $where[] = 'price_usd >= %f';
        $where_values[] = floatval( $filters['price_min'] );
    }
    if ( ! empty( $filters['price_max'] ) ) {
        $where[] = 'price_usd <= %f';
        $where_values[] = floatval( $filters['price_max'] );
    }
    if ( ! empty( $filters['year_min'] ) ) {
        $where[] = 'year >= %d';
        $where_values[] = intval( $filters['year_min'] );
    }
    if ( ! empty( $filters['year_max'] ) ) {
        $where[] = 'year <= %d';
        $where_values[] = intval( $filters['year_max'] );
    }
    if ( ! empty( $filters['loa_min'] ) ) {
        $where[] = 'loa_feet >= %f';
        $where_values[] = floatval( $filters['loa_min'] );
    }
    if ( ! empty( $filters['loa_max'] ) ) {
        $where[] = 'loa_feet <= %f';
        $where_values[] = floatval( $filters['loa_max'] );
    }
    if ( ! empty( $filters['builder'] ) ) {
        $where[] = 'builder = %s';
        $where_values[] = sanitize_text_field( $filters['builder'] );
    }
    if ( ! empty( $filters['category'] ) ) {
        $where[] = 'category = %s';
        $where_values[] = sanitize_text_field( $filters['category'] );
    }
    if ( ! empty( $filters['type'] ) ) {
        $where[] = 'type = %s';
        $where_values[] = sanitize_text_field( $filters['type'] );
    }
    if ( ! empty( $filters['condition'] ) ) {
        $where[] = 'condition = %s';
        $where_values[] = sanitize_text_field( $filters['condition'] );
    }
    
    $where_sql = implode( ' AND ', $where );
    
    // Build query
    $query = "SELECT * FROM $table_name WHERE $where_sql ORDER BY last_updated DESC LIMIT %d";
    $where_values[] = intval( $limit );
    
    if ( ! empty( $where_values ) ) {
        $prepared = $wpdb->prepare( $query, $where_values );
        $results = $wpdb->get_results( $prepared, ARRAY_A );
    } else {
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY last_updated DESC LIMIT %d", $limit ), ARRAY_A );
    }
    
    // Convert to vessel data format
    $vessels = array();
    foreach ( $results as $row ) {
        $vessels[] = array(
            'id' => $row['vessel_id'],
            'post_id' => 0,
            'name' => $row['name'],
            'price' => $row['price_usd'] ? '$' . number_format( $row['price_usd'] ) : '',
            'price_usd' => $row['price_usd'],
            'price_eur' => $row['price_eur'],
            'year' => $row['year'],
            'loa' => $row['loa_feet'] ? $row['loa_feet'] . ' ft' : '',
            'loa_feet' => $row['loa_feet'],
            'loa_meters' => $row['loa_meters'],
            'builder' => $row['builder'],
            'category' => $row['category'],
            'type' => $row['type'],
            'condition' => $row['condition'],
            'state_rooms' => $row['state_rooms'],
            'location' => $row['location'],
            'image' => $row['image_url'],
            'link' => $row['yatco_listing_url'],
        );
    }
    
    return $vessels;
}

/**
 * Get unique values for filter dropdowns from lightweight storage.
 * 
 * @return array Array with 'builders', 'categories', 'types', 'conditions' keys
 */
function yatco_api_only_get_filter_values() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'yatco_vessels_lightweight';
    
    $builders = $wpdb->get_col( "SELECT DISTINCT builder FROM $table_name WHERE builder != '' ORDER BY builder ASC" );
    $categories = $wpdb->get_col( "SELECT DISTINCT category FROM $table_name WHERE category != '' ORDER BY category ASC" );
    $types = $wpdb->get_col( "SELECT DISTINCT type FROM $table_name WHERE type != '' ORDER BY type ASC" );
    $conditions = $wpdb->get_col( "SELECT DISTINCT condition FROM $table_name WHERE condition != '' ORDER BY condition ASC" );
    
    return array(
        'builders' => $builders ? $builders : array(),
        'categories' => $categories ? $categories : array(),
        'types' => $types ? $types : array(),
        'conditions' => $conditions ? $conditions : array(),
    );
}

/**
 * Sync lightweight storage with API.
 * Updates lightweight data for all active vessels.
 * 
 * @param string $token API token
 * @param int    $batch_size Number of vessels to process per batch
 * @return array|WP_Error Array with 'processed', 'updated', 'errors' keys
 */
function yatco_api_only_sync_lightweight( $token, $batch_size = 50 ) {
    // Get all active vessel IDs
    $vessel_ids = yatco_api_only_get_vessel_ids( $token );
    
    if ( is_wp_error( $vessel_ids ) ) {
        return $vessel_ids;
    }
    
    $processed = 0;
    $updated = 0;
    $errors = 0;
    
    // Process in batches
    $batches = array_chunk( $vessel_ids, $batch_size );
    
    foreach ( $batches as $batch ) {
        foreach ( $batch as $vessel_id ) {
            $processed++;
            
            // Get vessel data from API (cached)
            $vessel_data = yatco_api_only_get_vessel_data( $token, $vessel_id );
            
            if ( is_wp_error( $vessel_data ) ) {
                $errors++;
                continue;
            }
            
            // Store lightweight data
            $result = yatco_api_only_store_lightweight( $vessel_data );
            
            if ( ! is_wp_error( $result ) ) {
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
 * Clear all lightweight storage data.
 */
function yatco_api_only_clear_lightweight() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'yatco_vessels_lightweight';
    $wpdb->query( "TRUNCATE TABLE $table_name" );
}

