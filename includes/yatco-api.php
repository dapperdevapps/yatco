<?php
/**
 * API Functions
 * 
 * Handles all YATCO API interactions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper: get Basic token.
 */
function yatco_get_token() {
    $options = get_option( 'yatco_api_settings' );
    return isset( $options['yatco_api_token'] ) ? trim( $options['yatco_api_token'] ) : '';
}

/**
 * Helper: fetch active vessel IDs using activevesselmlsid.
 */
function yatco_get_active_vessel_ids( $token, $max_records = 50 ) {
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/vessel/activevesselmlsid';

    $response = wp_remote_get(
        $endpoint,
        array(
            'headers' => array(
                'Authorization' => 'Basic ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 30,
        )
    );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'yatco_http_error', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
    }

        $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! is_array( $data ) ) {
        return new WP_Error( 'yatco_parse_error', 'Could not parse activevesselmlsid response.' );
    }

    $ids = array();
    foreach ( $data as $id ) {
        if ( is_numeric( $id ) ) {
            $ids[] = (int) $id;
        }
    }

    // Only limit if explicitly requested and max_records > 0
    // If max_records is 0, return all IDs
    if ( $max_records > 0 && count( $ids ) > $max_records ) {
        $ids = array_slice( $ids, 0, $max_records );
    }
    // If max_records is 0, return all IDs without limiting

    return $ids;
}

/**
 * Helper: fetch basic vessel details as fallback when FullSpecsAll is not available.
 * Try the same endpoint structure as yatco_get_active_vessel_ids uses for individual vessels.
 */
function yatco_fetch_basic_details( $token, $vessel_id ) {
    // Use the same endpoint pattern as the active vessel list - just get basic vessel info
    // The base ForSale/Vessel/{id} endpoint should return basic vessel data
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id );

    $response = wp_remote_get(
        $endpoint,
        array(
            'headers' => array(
                'Authorization' => 'Basic ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 15,
            'connect_timeout' => 8,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code !== 200 ) {
        return new WP_Error( 'yatco_http_error_basic', 'HTTP ' . $response_code . ' for basic vessel endpoint' );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    $json_error = json_last_error();
    if ( $json_error !== JSON_ERROR_NONE && $data === null ) {
        return new WP_Error( 'yatco_parse_error_basic', 'Could not parse basic vessel JSON: ' . json_last_error_msg() );
    }
    
    if ( $data === null || ( is_array( $data ) && empty( $data ) ) ) {
        return new WP_Error( 'yatco_no_data_basic', 'Basic vessel endpoint returned null or empty data.' );
    }

    // The base endpoint returns data in a simpler structure - wrap it to match FullSpecsAll format
    // Try to preserve existing structure if it has Result/BasicInfo, otherwise wrap everything in Result
    if ( ! isset( $data['Result'] ) && ! isset( $data['BasicInfo'] ) ) {
        // Wrap the response to match expected structure
        $wrapped_data = array(
            'Result' => $data, // All basic data goes in Result
            'BasicInfo' => isset( $data['BasicInfo'] ) ? $data['BasicInfo'] : ( isset( $data['BoatName'] ) ? $data : array() ),
        );
        return $wrapped_data;
    }

    return $data;
}

/**
 * Helper: fetch FullSpecsAll for a vessel, with fallback to basic details if FullSpecsAll is not available.
 */
function yatco_fetch_fullspecs( $token, $vessel_id ) {
    // Check stop flag right before API call (check both option and transient) - DON'T DELETE IT
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( "🛑 API: Stop flag detected before API call for vessel {$vessel_id}, cancelling", 'warning' );
        return new WP_Error( 'import_stopped', 'Import stopped by user.' );
    }
    
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll';

    $response = wp_remote_get(
        $endpoint,
        array(
            'headers' => array(
                'Authorization' => 'Basic ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20, // Reduced from 30 to 20 seconds to prevent hanging
            'connect_timeout' => 10, // Connection timeout
        )
    );
    
    // Check stop flag immediately after API call (check both option and transient) - DON'T DELETE IT
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( "🛑 API: Stop flag detected after API call for vessel {$vessel_id}, cancelling", 'warning' );
        return new WP_Error( 'import_stopped', 'Import stopped by user.' );
    }

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'yatco_http_error', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    // Check if JSON decode failed (error occurred)
    $json_error = json_last_error();
    if ( $json_error !== JSON_ERROR_NONE && $data === null ) {
        return new WP_Error( 'yatco_parse_error', 'Could not parse FullSpecsAll JSON: ' . json_last_error_msg() );
    }
    
    // Check if API returned null (valid JSON but no data available for this vessel)
    if ( $data === null || ( is_array( $data ) && empty( $data ) ) ) {
        // Try fallback to basic vessel endpoint
        yatco_log( "Import: FullSpecsAll returned null for vessel {$vessel_id}, trying basic vessel endpoint as fallback", 'info' );
        $basic_data = yatco_fetch_basic_details( $token, $vessel_id );
        
        if ( ! is_wp_error( $basic_data ) && ! empty( $basic_data ) ) {
            yatco_log( "Import: Successfully fetched basic vessel data for vessel {$vessel_id} (fallback)", 'info' );
            // Mark this as partial data so import function knows to handle it differently
            if ( is_array( $basic_data ) ) {
                $basic_data['_is_partial_data'] = true;
            }
            return $basic_data;
        } else {
            // Both endpoints failed
            $error_msg = is_wp_error( $basic_data ) ? $basic_data->get_error_message() : 'No data returned';
            yatco_log( "Import: Both FullSpecsAll and basic vessel endpoint failed for vessel {$vessel_id}: {$error_msg}", 'warning' );
            return new WP_Error( 'yatco_no_data', 'API returned null for FullSpecsAll and fallback basic vessel endpoint also failed. The vessel may be inactive or restricted.' );
        }
    }

    return $data;
}

/**
 * Connection test helper.
 */
function yatco_test_connection( $token ) {
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/vessel/activevesselmlsid';

    $response = wp_remote_get(
        $endpoint,
        array(
            'headers' => array(
            'Authorization' => 'Basic ' . $token,
            'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        )
    );

    if ( is_wp_error( $response ) ) {
        return '<div class="notice notice-error"><p>Error: ' . esc_html( $response->get_error_message() ) . '</p></div>';
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( 200 !== $code ) {
        return '<div class="notice notice-error"><p>Failed: HTTP ' . intval( $code ) . '</p><pre>' . esc_html( substr( $body, 0, 400 ) ) . '</pre></div>';
    }

    $data = json_decode( $body, true );
    if ( ! is_array( $data ) ) {
        return '<div class="notice notice-error"><p>Unexpected response format.</p></div>';
    }

    $count = count( $data );
    return '<div class="notice notice-success"><p>Connection successful! Found ' . number_format( $count ) . ' active vessel ID(s).</p></div>';
}
