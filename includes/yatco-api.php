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
 * 
 * Tries multiple endpoints including alternative paths for vessels not in ForSale category.
 */
function yatco_fetch_basic_details( $token, $vessel_id ) {
    // Try multiple endpoints - /Details endpoint often has better structure with Result/BasicInfo sections
    // Also try alternative paths for vessels that may not be in the ForSale category
    $endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details',
        'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $vessel_id ) . '/Details', // Alternative path
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ),
        'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $vessel_id ), // Alternative path
    );
    
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

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            continue; // Try next endpoint
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        $json_error = json_last_error();
        if ( $json_error !== JSON_ERROR_NONE && $data === null ) {
            continue; // Try next endpoint
        }
        
        if ( $data === null || ( is_array( $data ) && empty( $data ) ) ) {
            continue; // Try next endpoint
        }

        // If we got valid data with Result/BasicInfo structure, return it as-is
        if ( isset( $data['Result'] ) || isset( $data['BasicInfo'] ) ) {
            return $data;
        }
        
        // If data is flat, wrap it to match FullSpecsAll format
        // All flat data goes in Result, and BasicInfo gets a copy for compatibility
        $wrapped_data = array(
            'Result' => $data, // All basic data goes in Result
            'BasicInfo' => $data, // Also copy to BasicInfo for compatibility
        );
        return $wrapped_data;
    }
    
    // All endpoints failed
    return new WP_Error( 'yatco_no_data_basic', 'All basic vessel endpoints failed or returned no data.' );
}

/**
 * Helper: fetch FullSpecsAll for a vessel by MLS ID.
 * This is used as a fallback when Vessel ID lookup fails.
 * 
 * @param string $token API token
 * @param string|int $mls_id MLS ID
 * @return array|WP_Error Vessel data or error
 */
function yatco_fetch_fullspecs_by_mlsid( $token, $mls_id ) {
    // Check stop flag
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( "🛑 API: Stop flag detected before API call for MLS ID {$mls_id}, cancelling", 'warning' );
        return new WP_Error( 'import_stopped', 'Import stopped by user.' );
    }
    
    // Try multiple endpoints with MLS ID
    $endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . urlencode( $mls_id ) . '/Details/FullSpecsAll',
        'https://api.yatcoboss.com/api/v1/Vessel/' . urlencode( $mls_id ) . '/Details/FullSpecsAll',
    );
    
    foreach ( $endpoints as $endpoint ) {
        yatco_log( "API: Trying MLS ID endpoint: {$endpoint}", 'debug' );
        
        $response = wp_remote_get(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $token,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 20,
                'connect_timeout' => 10,
            )
        );
        
        // Check stop flag
        $stop_flag = get_option( 'yatco_import_stop_flag', false );
        if ( $stop_flag === false ) {
            $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        }
        if ( $stop_flag !== false ) {
            yatco_log( "🛑 API: Stop flag detected after API call for MLS ID {$mls_id}, cancelling", 'warning' );
            return new WP_Error( 'import_stopped', 'Import stopped by user.' );
        }

        if ( is_wp_error( $response ) ) {
            yatco_log( "API: WP_Error for MLS ID {$mls_id} on endpoint {$endpoint}: " . $response->get_error_message(), 'debug' );
            continue;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            yatco_log( "API: HTTP {$response_code} for MLS ID {$mls_id} on endpoint {$endpoint}", 'debug' );
            continue;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        $json_error = json_last_error();
        if ( $json_error !== JSON_ERROR_NONE && $data === null ) {
            yatco_log( "API: JSON parse error for MLS ID {$mls_id} on endpoint {$endpoint}: " . json_last_error_msg(), 'debug' );
            continue;
        }
        
        if ( $data === null || ( is_array( $data ) && empty( $data ) ) ) {
            yatco_log( "API: Empty/null response for MLS ID {$mls_id} on endpoint {$endpoint}", 'debug' );
            continue;
        }
        
        // Success!
        yatco_log( "API: Successfully fetched FullSpecsAll for MLS ID {$mls_id} from endpoint {$endpoint}", 'debug' );
        return $data;
    }
    
    // Try basic endpoints as fallback
    $basic_endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . urlencode( $mls_id ) . '/Details',
        'https://api.yatcoboss.com/api/v1/Vessel/' . urlencode( $mls_id ) . '/Details',
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . urlencode( $mls_id ),
        'https://api.yatcoboss.com/api/v1/Vessel/' . urlencode( $mls_id ),
    );
    
    foreach ( $basic_endpoints as $endpoint ) {
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
            continue;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            continue;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        $json_error = json_last_error();
        if ( $json_error !== JSON_ERROR_NONE && $data === null ) {
            continue;
        }
        
        if ( $data === null || ( is_array( $data ) && empty( $data ) ) ) {
            continue;
        }

        // If we got valid data, return it
        if ( isset( $data['Result'] ) || isset( $data['BasicInfo'] ) ) {
            return $data;
        }
        
        // Wrap flat data
        $wrapped_data = array(
            'Result' => $data,
            'BasicInfo' => $data,
        );
        return $wrapped_data;
    }
    
    return new WP_Error( 'yatco_no_data_mlsid', 'All MLS ID endpoints failed or returned no data.' );
}

/**
 * Helper: fetch FullSpecsAll for a vessel, with fallback to basic details if FullSpecsAll is not available.
 * 
 * Tries multiple approaches:
 * 1. Vessel ID endpoints (primary)
 * 2. MLS ID endpoints (if MLS ID is provided and Vessel ID fails)
 * 3. Basic endpoints as fallback
 * 
 * @param string $token API token
 * @param int|string $vessel_id Vessel ID (can also be MLS ID if $mls_id is not provided)
 * @param string|int|null $mls_id Optional MLS ID to try if Vessel ID fails
 * @return array|WP_Error Vessel data or error
 */
function yatco_fetch_fullspecs( $token, $vessel_id, $mls_id = null ) {
    // Check stop flag right before API call (check both option and transient) - DON'T DELETE IT
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag === false ) {
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
    }
    if ( $stop_flag !== false ) {
        yatco_log( "🛑 API: Stop flag detected before API call for vessel {$vessel_id}, cancelling", 'warning' );
        return new WP_Error( 'import_stopped', 'Import stopped by user.' );
    }
    
    // Try multiple endpoints - some vessels may not be in the ForSale category
    $endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll',
        'https://api.yatcoboss.com/api/v1/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll', // Alternative path
    );
    
    foreach ( $endpoints as $endpoint ) {
        yatco_log( "API: Trying endpoint for vessel {$vessel_id}: {$endpoint}", 'debug' );
        
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
            yatco_log( "API: WP_Error for vessel {$vessel_id} on endpoint {$endpoint}: " . $response->get_error_message(), 'debug' );
            continue; // Try next endpoint
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            yatco_log( "API: HTTP {$response_code} for vessel {$vessel_id} on endpoint {$endpoint}", 'debug' );
            continue; // Try next endpoint
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Check if JSON decode failed (error occurred)
        $json_error = json_last_error();
        if ( $json_error !== JSON_ERROR_NONE && $data === null ) {
            yatco_log( "API: JSON parse error for vessel {$vessel_id} on endpoint {$endpoint}: " . json_last_error_msg(), 'debug' );
            continue; // Try next endpoint
        }
        
        // Check if API returned null or empty (valid JSON but no data available for this vessel)
        if ( $data === null || ( is_array( $data ) && empty( $data ) ) ) {
            yatco_log( "API: Empty/null response for vessel {$vessel_id} on endpoint {$endpoint}", 'debug' );
            continue; // Try next endpoint
        }
        
        // Success! Found data on this endpoint
        yatco_log( "API: Successfully fetched FullSpecsAll for vessel {$vessel_id} from endpoint {$endpoint}", 'debug' );
        return $data;
    }
    
    // All FullSpecsAll endpoints failed, try basic vessel endpoints as fallback
    yatco_log( "Import: All FullSpecsAll endpoints returned null/empty for vessel {$vessel_id}, trying basic vessel endpoints as fallback", 'info' );
    $basic_data = yatco_fetch_basic_details( $token, $vessel_id );
    
    if ( ! is_wp_error( $basic_data ) && ! empty( $basic_data ) ) {
        yatco_log( "Import: Successfully fetched basic vessel data for vessel {$vessel_id} (fallback)", 'info' );
        // Mark this as partial data so import function knows to handle it differently
        if ( is_array( $basic_data ) ) {
            $basic_data['_is_partial_data'] = true;
        }
        return $basic_data;
    }
    
    // If Vessel ID failed and we have an MLS ID, try fetching by MLS ID
    // If mls_id is the same as vessel_id, it means we should try MLS ID endpoints with the same ID
    if ( ! empty( $mls_id ) ) {
        if ( $mls_id == $vessel_id ) {
            yatco_log( "Import: Vessel ID {$vessel_id} failed, trying same ID as MLS ID", 'info' );
        } else {
            yatco_log( "Import: Vessel ID {$vessel_id} failed, trying MLS ID {$mls_id} as alternative", 'info' );
        }
        $mlsid_data = yatco_fetch_fullspecs_by_mlsid( $token, $mls_id );
        
        if ( ! is_wp_error( $mlsid_data ) && ! empty( $mlsid_data ) ) {
            yatco_log( "Import: Successfully fetched vessel data using MLS ID {$mls_id} (alternative to Vessel ID {$vessel_id})", 'info' );
            return $mlsid_data;
        }
    }
    
    // All endpoints failed
    $error_msg = is_wp_error( $basic_data ) ? $basic_data->get_error_message() : 'No data returned';
    $mlsid_error = '';
    if ( ! empty( $mls_id ) ) {
        if ( $mls_id == $vessel_id ) {
            $mlsid_error = ' (also tried as MLS ID)';
        } else {
            $mlsid_error = ' (also tried MLS ID: ' . $mls_id . ')';
        }
    }
    yatco_log( "Import: All endpoints failed for vessel {$vessel_id}{$mlsid_error}: {$error_msg}", 'warning' );
    return new WP_Error( 'yatco_no_data', 'All API endpoints returned null/empty for this vessel. The vessel may be inactive, restricted, or require different API access.' );
}

/**
 * Debug helper: Test a specific vessel ID and return detailed API response information.
 * 
 * @param string $token API token
 * @param int    $vessel_id Vessel ID to test
 * @return string HTML output with debug information
 */
function yatco_debug_vessel_id( $token, $vessel_id ) {
    $vessel_id = intval( $vessel_id );
    $output = '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    $output .= '<h3>Debug: Vessel ID ' . esc_html( $vessel_id ) . '</h3>';
    
    // Check if vessel is in active list
    $active_ids = yatco_get_active_vessel_ids( $token, 0 );
    $is_in_active_list = false;
    if ( ! is_wp_error( $active_ids ) && is_array( $active_ids ) ) {
        $is_in_active_list = in_array( $vessel_id, array_map( 'intval', $active_ids ) );
    }
    
    $output .= '<p><strong>In Active List:</strong> ' . ( $is_in_active_list ? '✅ Yes' : '❌ No' ) . '</p>';
    if ( ! $is_in_active_list ) {
        $output .= '<p style="color: #856404; background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0;">';
        $output .= '<strong>Note:</strong> Vessel is not in the active list, but it may still be accessible via direct API call.';
        $output .= '</p>';
    }
    
    // Test all endpoints
    $endpoints_to_test = array(
        'FullSpecsAll (ForSale)' => 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . $vessel_id . '/Details/FullSpecsAll',
        'FullSpecsAll (Direct)' => 'https://api.yatcoboss.com/api/v1/Vessel/' . $vessel_id . '/Details/FullSpecsAll',
        'Details (ForSale)' => 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . $vessel_id . '/Details',
        'Details (Direct)' => 'https://api.yatcoboss.com/api/v1/Vessel/' . $vessel_id . '/Details',
        'Basic (ForSale)' => 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . $vessel_id,
        'Basic (Direct)' => 'https://api.yatcoboss.com/api/v1/Vessel/' . $vessel_id,
    );
    
    $output .= '<h4>Testing All Endpoints:</h4>';
    $output .= '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
    $output .= '<thead><tr style="background: #f0f0f0;"><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Endpoint</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Status</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Response</th></tr></thead>';
    $output .= '<tbody>';
    
    $found_working_endpoint = false;
    
    foreach ( $endpoints_to_test as $endpoint_name => $endpoint_url ) {
        $response = wp_remote_get(
            $endpoint_url,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $token,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 15,
            )
        );
        
        $status = '❌ Failed';
        $response_info = '';
        
        if ( is_wp_error( $response ) ) {
            $response_info = 'Error: ' . esc_html( $response->get_error_message() );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( $response_code === 200 ) {
                if ( $data !== null && ! empty( $data ) ) {
                    $status = '✅ Success';
                    $found_working_endpoint = true;
                    $response_info = 'HTTP 200 - Data returned (' . strlen( $body ) . ' bytes)';
                    if ( is_array( $data ) ) {
                        $keys = array_keys( $data );
                        $response_info .= ' - Keys: ' . esc_html( implode( ', ', array_slice( $keys, 0, 5 ) ) ) . ( count( $keys ) > 5 ? '...' : '' );
                    }
                } else {
                    $status = '⚠️ Empty';
                    $response_info = 'HTTP 200 - But response is null or empty';
                }
            } else {
                $response_info = 'HTTP ' . $response_code;
                if ( strlen( $body ) > 0 ) {
                    $response_info .= ' - ' . esc_html( substr( $body, 0, 100 ) );
                }
            }
        }
        
        $output .= '<tr>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;"><code style="font-size: 11px;">' . esc_html( $endpoint_name ) . '</code></td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $status . '</td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd; font-size: 12px;">' . $response_info . '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</tbody></table>';
    
    // Test using the main function
    $output .= '<h4>Testing with yatco_fetch_fullspecs():</h4>';
    $fullspecs = yatco_fetch_fullspecs( $token, $vessel_id );
    
    if ( is_wp_error( $fullspecs ) ) {
        $output .= '<p style="color: #dc3232;">❌ Error: ' . esc_html( $fullspecs->get_error_message() ) . '</p>';
    } elseif ( $fullspecs === null || empty( $fullspecs ) ) {
        $output .= '<p style="color: #ff9800;">⚠️ Returned null or empty</p>';
    } else {
        $output .= '<p style="color: #46b450;">✅ Success! Data retrieved.</p>';
        if ( is_array( $fullspecs ) ) {
            $keys = array_keys( $fullspecs );
            $output .= '<p>Data sections: ' . esc_html( implode( ', ', array_slice( $keys, 0, 10 ) ) ) . ( count( $keys ) > 10 ? '...' : '' ) . '</p>';
        }
    }
    
    if ( ! $found_working_endpoint ) {
        $output .= '<div style="background: #fce8e6; border-left: 4px solid #dc3232; padding: 15px; margin: 15px 0;">';
        $output .= '<p><strong>⚠️ No working endpoint found</strong></p>';
        $output .= '<p>This vessel may require different API access or may not be available through the standard endpoints.</p>';
        $output .= '<p>If the vessel is visible on yatco.com, it might be using a different API path or require special permissions.</p>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    return $output;
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
    $output = '<div class="notice notice-success"><p><strong>✅ Connection successful!</strong> Found ' . number_format( $count ) . ' active vessel ID(s).</p></div>';
    
    // Add button to view all vessel IDs
    $vessel_ids_json = wp_json_encode( $data, JSON_PRETTY_PRINT );
    $vessel_ids_json_escaped = esc_js( $vessel_ids_json );
    
    $output .= '<div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
    $output .= '<h3 style="margin-top: 0;">All Vessel IDs</h3>';
    $output .= '<p style="color: #666; font-size: 13px; margin-bottom: 15px;">View and search all ' . number_format( $count ) . ' vessel IDs from the API:</p>';
    
    $output .= '<div style="margin-bottom: 15px;">';
    $output .= '<button type="button" id="yatco-toggle-vessel-ids" class="button button-secondary" style="margin-right: 10px;">📋 View All Vessel IDs</button>';
    $output .= '<input type="text" id="yatco-vessel-ids-search" placeholder="Search vessel IDs (Ctrl+F also works)..." class="regular-text" style="width: 350px; display: none;" />';
    $output .= '</div>';
    
    $output .= '<div id="yatco-vessel-ids-display" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; border-radius: 4px; padding: 20px; max-height: 700px; overflow: auto; font-family: "Courier New", Courier, monospace; font-size: 13px; line-height: 1.6; display: none; position: relative;">';
    $output .= '<pre id="yatco-vessel-ids-content" style="margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #d4d4d4;">';
    $output .= esc_html( $vessel_ids_json );
    $output .= '</pre>';
    $output .= '</div>';
    
    // JavaScript for toggle and search functionality
    $output .= '<script type="text/javascript">';
    $output .= 'jQuery(document).ready(function($) {';
    $output .= '    var $toggleBtn = $("#yatco-toggle-vessel-ids");';
    $output .= '    var $searchBox = $("#yatco-vessel-ids-search");';
    $output .= '    var $idsDisplay = $("#yatco-vessel-ids-display");';
    $output .= '    var $idsContent = $("#yatco-vessel-ids-content");';
    $output .= '    var originalContent = ' . wp_json_encode( $vessel_ids_json ) . ';';
    $output .= '    var isExpanded = false;';
    $output .= '    ';
    $output .= '    // Toggle button functionality';
    $output .= '    $toggleBtn.on("click", function() {';
    $output .= '        if (isExpanded) {';
    $output .= '            $idsDisplay.slideUp(300);';
    $output .= '            $searchBox.slideUp(200);';
    $output .= '            $toggleBtn.text("📋 View All Vessel IDs");';
    $output .= '            isExpanded = false;';
    $output .= '            $searchBox.val("");';
    $output .= '            $idsContent.text(originalContent);';
    $output .= '        } else {';
    $output .= '            $idsDisplay.slideDown(300);';
    $output .= '            $searchBox.slideDown(200);';
    $output .= '            $toggleBtn.text("🔽 Hide Vessel IDs");';
    $output .= '            isExpanded = true;';
    $output .= '            $searchBox.focus();';
    $output .= '        }';
    $output .= '    });';
    $output .= '    ';
    $output .= '    // Search functionality with highlighting';
    $output .= '    var searchTimeout;';
    $output .= '    $searchBox.on("input keyup", function(e) {';
    $output .= '        // Allow Ctrl+F to work naturally';
    $output .= '        if (e.ctrlKey && e.key === "f") {';
    $output .= '            return;';
    $output .= '        }';
    $output .= '        ';
    $output .= '        var searchTerm = $(this).val();';
    $output .= '        clearTimeout(searchTimeout);';
    $output .= '        ';
    $output .= '        if (searchTerm === "") {';
    $output .= '            $idsContent.text(originalContent);';
    $output .= '            return;';
    $output .= '        }';
    $output .= '        ';
    $output .= '        searchTimeout = setTimeout(function() {';
    $output .= '            var regex = new RegExp("(" + searchTerm.replace(/[.*+?^${}()|[\\]\\\\]/g, "\\\\$&") + ")", "gi");';
    $output .= '            var highlightedContent = originalContent.replace(regex, "<mark style=\'background: #ffeb3b; color: #000; padding: 2px 4px; border-radius: 2px;\'>$1</mark>");';
    $output .= '            $idsContent.html(highlightedContent);';
    $output .= '            ';
    $output .= '            // Scroll to first match';
    $output .= '            var firstMark = $idsContent.find("mark").first();';
    $output .= '            if (firstMark.length) {';
    $output .= '                $idsDisplay.animate({';
    $output .= '                    scrollTop: firstMark.offset().top - $idsDisplay.offset().top + $idsDisplay.scrollTop() - 100';
    $output .= '                }, 300);';
    $output .= '            }';
    $output .= '        }, 300);';
    $output .= '    });';
    $output .= '});';
    $output .= '</script>';
    
    $output .= '</div>';
    
    return $output;
}

