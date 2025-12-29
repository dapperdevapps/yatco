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
 * Convert MLS ID to Vessel ID using the conversion endpoint.
 * 
 * Endpoint: /api/v1/ForSale/Vessel/VesselID/{MLSID}
 * 
 * @param string $token API token
 * @param int|string $mls_id MLS ID to convert
 * @return int|WP_Error Vessel ID on success, WP_Error on failure
 */
function yatco_convert_mlsid_to_vessel_id( $token, $mls_id ) {
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/VesselID/' . intval( $mls_id );
    
    $response = wp_remote_get(
        $endpoint,
        array(
            'headers' => array(
                'Authorization' => 'Basic ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        )
    );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $code ) {
        return new WP_Error( 'yatco_http_error', "HTTP {$code} when converting MLS ID {$mls_id} to Vessel ID" );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    
    // The response should be a single Vessel ID (integer)
    if ( is_numeric( $data ) ) {
        return (int) $data;
    }
    
    // Some APIs might return it as a string or in an object
    if ( is_array( $data ) && isset( $data['VesselID'] ) && is_numeric( $data['VesselID'] ) ) {
        return (int) $data['VesselID'];
    }
    
    if ( is_array( $data ) && isset( $data['vesselID'] ) && is_numeric( $data['vesselID'] ) ) {
        return (int) $data['vesselID'];
    }
    
    return new WP_Error( 'yatco_parse_error', "Could not parse Vessel ID from conversion endpoint response for MLS ID {$mls_id}" );
}

/**
 * Convert Vessel ID to MLS ID using the conversion endpoint.
 * 
 * Endpoint: /api/v1/ForSale/Vessel/MLSID/{VesselID}
 * 
 * @param string $token API token
 * @param int|string $vessel_id Vessel ID to convert
 * @return int|WP_Error MLS ID on success, WP_Error on failure
 */
function yatco_convert_vessel_id_to_mlsid( $token, $vessel_id ) {
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/MLSID/' . intval( $vessel_id );
    
    $response = wp_remote_get(
        $endpoint,
        array(
            'headers' => array(
                'Authorization' => 'Basic ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        )
    );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $code ) {
        return new WP_Error( 'yatco_http_error', "HTTP {$code} when converting Vessel ID {$vessel_id} to MLS ID" );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    
    // The response should be a single MLS ID (integer)
    if ( is_numeric( $data ) ) {
        return (int) $data;
    }
    
    // Some APIs might return it as a string or in an object
    if ( is_array( $data ) && isset( $data['MLSID'] ) && is_numeric( $data['MLSID'] ) ) {
        return (int) $data['MLSID'];
    }
    
    if ( is_array( $data ) && isset( $data['mlsID'] ) && is_numeric( $data['mlsID'] ) ) {
        return (int) $data['mlsID'];
    }
    
    return new WP_Error( 'yatco_parse_error', "Could not parse MLS ID from conversion endpoint response for Vessel ID {$vessel_id}" );
}

/**
 * Helper: fetch active vessel IDs using activevesselmlsid.
 * 
 * NOTE: Based on the endpoint name "activevesselmlsid", this likely returns MLS IDs, not Vessel IDs.
 * We return them as-is and handle conversion in the import functions.
 * 
 * @param string $token API token
 * @param int $max_records Maximum number of IDs to return (0 = all)
 * @return array|WP_Error Array of IDs (likely MLS IDs) or WP_Error on failure
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

    return $ids;
}

/**
 * Helper: fetch basic vessel details as fallback when FullSpecsAll is not available.
 * Try the same endpoint structure as yatco_get_active_vessel_ids uses for individual vessels.
 * 
 * Tries multiple endpoints including alternative paths for vessels not in ForSale category.
 */
function yatco_fetch_basic_details( $token, $vessel_id, $mls_id = null ) {
    // Try multiple endpoints - /Details endpoint often has better structure with Result/BasicInfo sections
    // Also try alternative paths for vessels that may not be in the ForSale category
    $endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details',
        'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $vessel_id ) . '/Details', // Alternative path
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ),
        'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $vessel_id ), // Alternative path
    );
    
    // If MLS ID is provided, also try MLS ID endpoints
    if ( ! empty( $mls_id ) && $mls_id != $vessel_id ) {
        array_unshift( $endpoints, 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $mls_id ) . '/Details' );
        array_unshift( $endpoints, 'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $mls_id ) . '/Details' );
    }
    
    foreach ( $endpoints as $endpoint ) {
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
            continue; // Try next endpoint
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            continue; // Try next endpoint
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            continue; // Try next endpoint
        }

        if ( $data !== null && ( is_array( $data ) && ! empty( $data ) ) ) {
            // Mark as partial data since this is a basic endpoint
            $data['_is_partial_data'] = true;
            return $data;
        }
    }

    return new WP_Error( 'yatco_api_error', 'All basic detail endpoints failed for vessel ' . $vessel_id );
}

/**
 * Fetch full vessel specifications by MLS ID.
 * Tries multiple endpoints to find the vessel data.
 * 
 * @param string $token API token
 * @param int|string $mls_id MLS ID
 * @return array|WP_Error Vessel data array or WP_Error on failure
 */
function yatco_fetch_fullspecs_by_mlsid( $token, $mls_id ) {
    // Try multiple FullSpecsAll endpoints with MLS ID
    $endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $mls_id ) . '/Details/FullSpecsAll',
        'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $mls_id ) . '/Details/FullSpecsAll',
    );

    foreach ( $endpoints as $endpoint ) {
        $response = wp_remote_get(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $token,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 30,
                'connect_timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            continue; // Try next endpoint
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            continue; // Try next endpoint
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            continue; // Try next endpoint
        }

        if ( $data !== null && ( is_array( $data ) && ! empty( $data ) ) ) {
            return $data;
        }
    }

    // All FullSpecsAll endpoints failed, try basic MLS ID endpoints as fallback
    return yatco_fetch_basic_details( $token, $mls_id, $mls_id );
}

/**
 * Fetch full vessel specifications.
 * Tries multiple endpoints and falls back to basic details if FullSpecsAll fails.
 * 
 * @param string $token API token
 * @param int|string $vessel_id Vessel ID
 * @param int|string|null $mls_id Optional MLS ID to try if vessel_id fails
 * @return array|WP_Error Vessel data array or WP_Error on failure
 */
function yatco_fetch_fullspecs( $token, $vessel_id, $mls_id = null ) {
    // Check stop flag before making API call
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        return new WP_Error( 'import_stopped', 'Import stopped by user.' );
    }

    // Try multiple FullSpecsAll endpoints
    $endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll',
        'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll', // Alternative path
    );

    foreach ( $endpoints as $endpoint ) {
        $response = wp_remote_get(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $token,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 30,
                'connect_timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            continue; // Try next endpoint
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            continue; // Try next endpoint
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            continue; // Try next endpoint
        }

        if ( $data !== null && ( is_array( $data ) && ! empty( $data ) ) ) {
            return $data;
        }
    }

    // All FullSpecsAll endpoints failed, try basic vessel endpoints as fallback
    $basic_data = yatco_fetch_basic_details( $token, $vessel_id, $mls_id );
    if ( ! is_wp_error( $basic_data ) && ! empty( $basic_data ) ) {
        return $basic_data;
    }

    // If Vessel ID failed and we have an MLS ID, try fetching by MLS ID
    if ( ! empty( $mls_id ) && $mls_id != $vessel_id ) {
        $mlsid_data = yatco_fetch_fullspecs_by_mlsid( $token, $mls_id );
        if ( ! is_wp_error( $mlsid_data ) && ! empty( $mlsid_data ) ) {
            return $mlsid_data;
        }
    }

    return new WP_Error( 'yatco_api_error', 'All endpoints failed for vessel ' . $vessel_id );
}
