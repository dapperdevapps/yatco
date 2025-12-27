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
    // Try multiple endpoints - /Details endpoint often has better structure with Result/BasicInfo sections
    $endpoints = array(
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details',
        'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ),
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
