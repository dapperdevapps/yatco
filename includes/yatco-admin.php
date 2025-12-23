<?php
/**
 * Admin Functions
 * 
 * Handles admin pages, settings, and import functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin settings page.
 */
function yatco_add_admin_menu() {
    add_options_page(
        'YATCO API Settings',
        'YATCO API',
        'manage_options',
        'yatco_api',
        'yatco_options_page'
    );
 
    // Import page under Yachts.
    add_submenu_page(
        'edit.php?post_type=yacht',
        'YATCO Import',
        'YATCO Import',
        'manage_options',
        'yatco_import',
        'yatco_import_page'
    );
}

function yatco_settings_init() {
    register_setting( 'yatco_api', 'yatco_api_settings' );

    add_settings_section(
        'yatco_api_section',
        'YATCO API Credentials',
        'yatco_settings_section_callback',
        'yatco_api'
    );

    add_settings_field(
        'yatco_api_token',
        'API Token (Basic)',
        'yatco_api_token_render',
        'yatco_api',
        'yatco_api_section'
    );

    add_settings_field(
        'yatco_cache_duration',
        'Cache Duration (minutes)',
        'yatco_cache_duration_render',
        'yatco_api',
        'yatco_api_section'
    );

    add_settings_field(
        'yatco_auto_refresh_cache',
        'Auto-Refresh Cache',
        'yatco_auto_refresh_cache_render',
        'yatco_api',
        'yatco_api_section'
    );
}

function yatco_settings_section_callback() {
    echo '<p>Enter your YATCO API Basic token. This will be used for search and import.</p>';
}

function yatco_api_token_render() {
    $options = get_option( 'yatco_api_settings' );
    $token   = isset( $options['yatco_api_token'] ) ? $options['yatco_api_token'] : '';
    echo '<input type="text" name="yatco_api_settings[yatco_api_token]" value="' . esc_attr( $token ) . '" size="80" />';
    echo '<p class="description">Paste the Basic token exactly as provided by YATCO (do not re-encode).</p>';
}

function yatco_cache_duration_render() {
    $options = get_option( 'yatco_api_settings' );
    $cache   = isset( $options['yatco_cache_duration'] ) ? intval( $options['yatco_cache_duration'] ) : 30;
    echo '<input type="number" step="1" min="1" name="yatco_api_settings[yatco_cache_duration]" value="' . esc_attr( $cache ) . '" />';
    echo '<p class="description">How long to cache vessel listings before refreshing (default: 30 minutes).</p>';
}

function yatco_auto_refresh_cache_render() {
    $options = get_option( 'yatco_api_settings' );
    $enabled = isset( $options['yatco_auto_refresh_cache'] ) ? $options['yatco_auto_refresh_cache'] : 'no';
    echo '<input type="checkbox" name="yatco_api_settings[yatco_auto_refresh_cache]" value="yes" ' . checked( $enabled, 'yes', false ) . ' />';
    echo '<label>Automatically refresh cache every 6 hours</label>';
    echo '<p class="description">Enable this to automatically pre-load the cache every 6 hours via WP-Cron.</p>';
}

/**
 * Settings page output.
 */
function yatco_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = get_option( 'yatco_api_settings' );
    $token   = isset( $options['yatco_api_token'] ) ? $options['yatco_api_token'] : '';

    // Get current tab
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
    $tabs = array(
        'settings' => 'Settings',
        'import' => 'Import',
        'testing' => 'Testing',
        'status' => 'Status',
        'troubleshooting' => 'Troubleshooting',
    );

    echo '<div class="wrap">';
    echo '<h1>YATCO API Settings</h1>';
    
    // Tab navigation
    echo '<nav class="nav-tab-wrapper" style="margin: 20px 0 0 0;">';
    foreach ( $tabs as $tab_key => $tab_label ) {
        $active = ( $current_tab === $tab_key ) ? ' nav-tab-active' : '';
        $url = admin_url( 'options-general.php?page=yatco_api&tab=' . $tab_key );
        echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . $active . '">' . esc_html( $tab_label ) . '</a>';
    }
    echo '</nav>';
    
    echo '<div class="yatco-tab-content" style="margin-top: 20px;">';
    
    // Settings Tab
    if ( $current_tab === 'settings' ) {
        echo '<div class="yatco-settings-section">';
        echo '<h2>API Configuration</h2>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'yatco_api' );
    do_settings_sections( 'yatco_api' );
    submit_button();
    echo '</form>';
        echo '</div>';
    }
    
    // Import Tab
    if ( $current_tab === 'import' ) {
        echo '<div class="yatco-import-section">';
        
        // Display Import Status & Progress at the top
        yatco_display_import_status_section();
        
        // Display Import Activity Log
        yatco_display_import_logs();
        
        echo '<hr style="margin: 30px 0;" />';
        echo '<h2>Import Vessels to CPT</h2>';
        echo '<p>Import all vessels into the Yacht Custom Post Type (CPT) for faster queries, better SEO, and individual vessel pages. This may take several minutes for 7000+ vessels.</p>';
        echo '<p><strong>Benefits of CPT import:</strong> Better performance with WP_Query, individual pages per vessel, improved SEO, easier management via WordPress admin.</p>';
        
        // Full Import Section
        echo '<hr style="margin: 30px 0;" />';
        echo '<h2>Full Import</h2>';
        echo '<div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0;">';
        echo '<p><strong>💡 Full Import Process:</strong></p>';
        echo '<p>Imports all active vessels with complete data (names, images, descriptions, specs, etc.) in one process.</p>';
        echo '<p style="margin-top: 10px;"><strong>Note:</strong> This may take a while depending on the number of vessels. Progress can be tracked in the Status tab. You can stop the import at any time using the stop button.</p>';
        echo '</div>';
        
        // Full Import Button
        echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
        echo '<h3>Full Import: Import All Vessels</h3>';
        echo '<p>Fetches all active vessels with complete data (names, images, descriptions, specs, etc.).</p>';
        echo '<div style="display: flex; gap: 10px; margin-top: 15px;">';
        echo '<form method="post" style="margin: 0;">';
        wp_nonce_field( 'yatco_full_import', 'yatco_full_import_nonce' );
        submit_button( 'Run Full Import (WP-Cron)', 'primary', 'yatco_full_import', false );
        echo '</form>';
        echo '<form method="post" style="margin: 0;">';
        wp_nonce_field( 'yatco_full_import_direct', 'yatco_full_import_direct_nonce' );
        submit_button( 'Run Full Import Directly (If WP-Cron Not Working)', 'secondary', 'yatco_full_import_direct', false );
        echo '</form>';
        echo '</div>';

        if ( isset( $_POST['yatco_full_import'] ) && check_admin_referer( 'yatco_full_import', 'yatco_full_import_nonce' ) ) {
            if ( empty( $token ) ) {
                yatco_log( 'Full Import: Import attempt failed - missing token', 'error' );
                echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
            } else {
                yatco_log( 'Full Import: Import triggered via WP-Cron button', 'info' );
                
                // Clear any existing progress
                delete_transient( 'yatco_import_progress' );
                delete_transient( 'yatco_cache_warming_stop' );
                
                // Initialize progress immediately so status bar appears
                set_transient( 'yatco_cache_warming_status', 'Full Import: Starting import...', 600 );
                set_transient( 'yatco_import_progress', array(
                    'last_processed' => 0,
                    'total' => 0,
                    'timestamp' => time(),
                    'percent' => 0,
                ), 3600 );
                
                // Schedule and trigger immediately
                $scheduled = wp_schedule_single_event( time(), 'yatco_full_import_hook' );
                yatco_log( 'Full Import: Scheduled WP-Cron event. Result: ' . ( $scheduled === false ? 'Failed' : 'Success' ), 'info' );
                
                // Force WP-Cron to run immediately
                yatco_log( 'Full Import: Calling spawn_cron()', 'info' );
                spawn_cron();
                
                // Also trigger wp-cron.php directly (non-blocking)
                yatco_log( 'Full Import: Triggering wp-cron.php directly', 'info' );
                $cron_response = wp_remote_post( 
                    site_url( 'wp-cron.php?doing_wp_cron' ),
                    array(
                        'blocking'  => false,
                        'timeout'   => 0.01,
                        'sslverify' => false,
                    )
                );
                if ( is_wp_error( $cron_response ) ) {
                    yatco_log( 'Full Import: wp-cron.php request failed: ' . $cron_response->get_error_message(), 'error' );
                } else {
                    yatco_log( 'Full Import: wp-cron.php request sent (non-blocking)', 'info' );
                }
                
                echo '<div class="notice notice-info"><p><strong>Full Import started!</strong> This will run in the background. Check the Status tab to see progress.</p></div>';
            }
        }
        
        // Direct run handler
        if ( isset( $_POST['yatco_full_import_direct'] ) && check_admin_referer( 'yatco_full_import_direct', 'yatco_full_import_direct_nonce' ) ) {
            if ( empty( $token ) ) {
                yatco_log( 'Full Import Direct: Import attempt failed - missing token', 'error' );
                echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
            } else {
                yatco_log( 'Full Import Direct: Import triggered via Direct button', 'info' );
                
                // Clear any existing progress and stop flags
                delete_transient( 'yatco_import_progress' );
                delete_transient( 'yatco_cache_warming_stop' );
                delete_option( 'yatco_import_stop_flag' );
                
                // Enable auto-resume for direct runs (will automatically continue if it times out)
                update_option( 'yatco_import_auto_resume', time(), false );
                
                // Initialize progress immediately
                set_transient( 'yatco_cache_warming_status', 'Full Import: Starting direct import (auto-resume enabled)...', 600 );
                set_transient( 'yatco_import_progress', array(
                    'last_processed' => 0,
                    'processed' => 0,
                    'total' => 0,
                    'timestamp' => time(),
                    'percent' => 0,
                ), 3600 );
                
                echo '<div class="notice notice-info"><p><strong>Full Import running directly with auto-resume enabled...</strong> If the import stops (e.g., due to timeout), it will automatically resume until all vessels are imported. <strong>Keep this page open</strong> to monitor progress in the Status tab.</p></div>';
                
                // Flush output so user sees the message
                if ( ob_get_level() > 0 ) {
                    ob_flush();
                }
                flush();
                
                // Run directly - set much higher limits for direct runs
                @set_time_limit( 0 ); // Try to set unlimited (may not work on all servers)
                @ini_set( 'max_execution_time', 0 ); // Try unlimited
                @ini_set( 'memory_limit', '512M' ); // Increase memory limit
                yatco_log( 'Full Import Direct: Execution time limit set to unlimited, memory limit increased to 512M', 'info' );
                yatco_log( 'Full Import Direct: Calling yatco_full_import()', 'info' );
                
                // Wrap in try-catch to catch fatal errors
                try {
                yatco_full_import( $token );
                yatco_log( 'Full Import Direct: Import function completed', 'info' );
                } catch ( Exception $e ) {
                    yatco_log( 'Full Import Direct: Fatal error occurred: ' . $e->getMessage(), 'error' );
                    set_transient( 'yatco_cache_warming_status', 'Full Import Error: ' . $e->getMessage(), 300 );
                    echo '<div class="notice notice-error"><p><strong>Import Error:</strong> ' . esc_html( $e->getMessage() ) . '</p></div>';
                }
                
                echo '<div class="notice notice-success"><p><strong>Full Import completed!</strong> Check the Status tab for details.</p></div>';
            }
        }
        echo '</div>';
        
        // Daily Sync Button
        echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
        echo '<h3>Daily Sync: Check for Changes</h3>';
        echo '<p>Checks for new vessels (imports them), removed vessels (marks as draft), and updates prices and days on market for existing vessels. This can run daily.</p>';
        $last_sync = get_option( 'yatco_daily_sync_last_run', 0 );
        if ( $last_sync > 0 ) {
            echo '<p style="color: #666; font-size: 13px;">Last run: ' . date( 'Y-m-d H:i:s', $last_sync ) . '</p>';
            $sync_results = get_option( 'yatco_daily_sync_results', array() );
            if ( ! empty( $sync_results ) ) {
                $removed = isset( $sync_results['removed'] ) ? intval( $sync_results['removed'] ) : 0;
                $new = isset( $sync_results['new'] ) ? intval( $sync_results['new'] ) : 0;
                $price_updates = isset( $sync_results['price_updates'] ) ? intval( $sync_results['price_updates'] ) : 0;
                $days_updates = isset( $sync_results['days_on_market_updates'] ) ? intval( $sync_results['days_on_market_updates'] ) : 0;
                echo '<p style="color: #666; font-size: 13px;">Last results: ' . 
                     $removed . ' removed, ' . 
                     $new . ' new, ' . 
                     $price_updates . ' price updates, ' . 
                     $days_updates . ' days on market updates.</p>';
            }
        }
        echo '<form method="post" style="margin-top: 15px;">';
        wp_nonce_field( 'yatco_daily_sync', 'yatco_daily_sync_nonce' );
        submit_button( 'Run Daily Sync', 'secondary', 'yatco_daily_sync', false );
        echo '</form>';
        
        if ( isset( $_POST['yatco_daily_sync'] ) && check_admin_referer( 'yatco_daily_sync', 'yatco_daily_sync_nonce' ) ) {
            if ( empty( $token ) ) {
                echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
            } else {
                // Run Daily Sync directly (it's fast)
                yatco_daily_sync_check( $token );
                echo '<div class="notice notice-success"><p><strong>Daily Sync completed!</strong> Check status below for results.</p></div>';
            }
        }
        echo '</div>';
        
        echo '<hr style="margin: 30px 0;" />';
        
        // Real-time progress bar update - using BOTH Heartbeat API AND AJAX polling for maximum reliability
        echo '<script type="text/javascript">';
        echo 'jQuery(document).ready(function($) {';
        echo '    var lastUpdateTime = 0;';
        echo '    var pollInterval = null;';
        echo '    ';
        echo '    function updateProgressBar(progressData) {';
        echo '        if (!progressData) {';
        echo '            return;';
        echo '        }';
        echo '        ';
        echo '        var percent = progressData.percent || 0;';
        echo '        var progressBar = $("#yatco-progress-bar");';
        echo '        if (progressBar.length) {';
        echo '            var currentWidth = parseFloat(progressBar.css("width").replace("%", "")) || 0;';
        echo '            if (Math.abs(currentWidth - percent) > 0.01) {';
        echo '                progressBar.css("width", percent + "%");';
        echo '                progressBar.text(percent.toFixed(1) + "%");';
        echo '            }';
        echo '        }';
        echo '        ';
        echo '        // Update status text';
        echo '        if (progressData.status && $("#yatco-status-text").length) {';
        echo '            $("#yatco-status-text").html("<strong>Status:</strong> " + progressData.status);';
        echo '        }';
        echo '        ';
        echo '        // Update counts';
        echo '        if (progressData.current !== undefined && $("#yatco-processed-count").length) {';
        echo '            $("#yatco-processed-count").text(progressData.current.toLocaleString() + " / " + progressData.total.toLocaleString());';
        echo '        }';
        echo '        if (progressData.remaining !== undefined && $("#yatco-remaining-count").length) {';
        echo '            $("#yatco-remaining-count").text(progressData.remaining.toLocaleString());';
        echo '        }';
        echo '        ';
        echo '        // Update ETA';
        echo '        if (progressData.eta_minutes !== undefined && $("#yatco-eta-text").length) {';
        echo '            $("#yatco-eta-text").text(progressData.eta_minutes + " minutes");';
        echo '        }';
        echo '        ';
        echo '        lastUpdateTime = Date.now();';
        echo '    }';
        echo '    ';
        echo '    function fetchProgress() {';
        echo '        $.ajax({';
        echo '            url: ajaxurl,';
        echo '            type: "POST",';
        echo '            data: {';
        echo '                action: "yatco_get_import_status",';
        echo '                _ajax_nonce: ' . json_encode( wp_create_nonce( 'yatco_get_import_status_nonce' ) ) . '';
        echo '            },';
        echo '            cache: false,';
        echo '            success: function(response) {';
        echo '                if (response && response.success && response.data && response.data.progress) {';
        echo '                    updateProgressBar(response.data.progress);';
        echo '                }';
        echo '            },';
        echo '            error: function() {';
        echo '                // Silent fail, will retry';
        echo '            }';
        echo '        });';
        echo '    }';
        echo '    ';
        echo '    // Listen to heartbeat tick (primary method)';
        echo '    $(document).on("heartbeat-tick", function(event, data) {';
        echo '        if (data.yatco_import_progress) {';
        echo '            updateProgressBar(data.yatco_import_progress);';
        echo '        }';
        echo '        if (data.yatco_auto_resume) {';
        echo '            console.log("YATCO: Auto-resuming import...");';
        echo '        }';
        echo '    });';
        echo '    ';
        echo '    // Also poll via AJAX every 1 second as backup (ensures real-time updates)';
        echo '    fetchProgress(); // Initial fetch';
        echo '    pollInterval = setInterval(fetchProgress, 1000); // Poll every 1 second';
        echo '    ';
        echo '    console.log("YATCO: Progress updates enabled (Heartbeat + AJAX polling every 1s)");';
        echo '});';
        echo '</script>';
        
        echo '<hr style="margin: 30px 0;" />';
        echo '<h2>Full Import (All Stages at Once)</h2>';
        echo '<p>This runs all stages sequentially. Use staged import above for better control.</p>';
        
        // Show cache warming status and controls
        $pre_cache_status = get_transient( 'yatco_cache_warming_status' );
        
        // Display cache warming status if available
        if ( $pre_cache_status !== false ) {
            echo '<div class="notice notice-info" style="margin: 20px 0;">';
            echo '<p><strong>Status:</strong> ' . esc_html( $pre_cache_status ) . '</p>';
            echo '</div>';
        }
        
        echo '</div>'; // Close yatco-import-section
    }
    
    // Status Tab - show cache warming status
    if ( $current_tab === 'status' ) {
        echo '<div class="yatco-status-section">';
        echo '<h2>Import Status & Progress</h2>';
        
        // Get all progress data
        $import_progress = get_transient( 'yatco_import_progress' );
        $cache_status = get_transient( 'yatco_cache_warming_status' );
        
        // Determine if import is active
        $active_stage = 0;
        $active_progress = null;
        if ( $import_progress !== false && is_array( $import_progress ) ) {
            $active_stage = 'full';
            $active_progress = $import_progress;
        }
        
        // Display status bar
        echo '<div id="yatco-import-status-display" style="background: #fff; border: 2px solid #2271b1; border-radius: 4px; padding: 20px; margin: 20px 0;">';
        
        if ( $active_stage > 0 || $active_stage === 'full' || $active_stage === 'daily_sync' ) {
            if ( $active_stage === 'daily_sync' ) {
                // Daily sync progress display
                $current = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : 0;
                $total = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
                $percent = $total > 0 ? round( ( $current / $total ) * 100, 1 ) : 0;
                $stage_name = 'Daily Sync';
            } else {
                // Full import progress display
                $current = isset( $active_progress['last_processed'] ) ? intval( $active_progress['last_processed'] ) : 0;
                $total = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
                $percent = isset( $active_progress['percent'] ) ? floatval( $active_progress['percent'] ) : ( $total > 0 ? round( ( $current / $total ) * 100, 1 ) : 0 );
                $stage_name = 'Full Import';
            }
            
            echo '<h3 style="margin-top: 0; color: #2271b1;">📊 ' . esc_html( $stage_name ) . ' Progress</h3>';
            
            if ( $cache_status !== false ) {
                echo '<p id="yatco-status-text" style="margin: 10px 0; font-size: 14px; color: #666;"><strong>Status:</strong> ' . esc_html( $cache_status ) . '</p>';
            } else {
                echo '<p id="yatco-status-text" style="margin: 10px 0; font-size: 14px; color: #666;"><strong>Status:</strong> <span>Starting...</span></p>';
            }
            
            echo '<div style="background: #f0f0f0; border-radius: 10px; height: 30px; margin: 15px 0; position: relative; overflow: hidden;">';
            echo '<div id="yatco-progress-bar" style="background: linear-gradient(90deg, #2271b1 0%, #46b450 100%); height: 100%; width: ' . esc_attr( $percent ) . '%; transition: width 0.5s ease-in-out; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 12px;">';
            echo esc_html( $percent ) . '%';
            echo '</div>';
            echo '</div>';
            
            echo '<div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px; color: #666;">';
            echo '<span><strong>Processed:</strong> <span id="yatco-processed-count">' . number_format( $current ) . ' / ' . number_format( $total ) . '</span></span>';
            echo '<span><strong>Remaining:</strong> <span id="yatco-remaining-count">' . number_format( $total - $current ) . '</span></span>';
            echo '</div>';
            
            // Show daily sync details
            if ( $active_stage === 'daily_sync' ) {
                $removed = isset( $active_progress['removed'] ) ? intval( $active_progress['removed'] ) : 0;
                $new = isset( $active_progress['new'] ) ? intval( $active_progress['new'] ) : 0;
                $price_updates = isset( $active_progress['price_updates'] ) ? intval( $active_progress['price_updates'] ) : 0;
                $days_updates = isset( $active_progress['days_on_market_updates'] ) ? intval( $active_progress['days_on_market_updates'] ) : 0;
                echo '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; font-size: 13px; color: #666;">';
                echo '<p style="margin: 5px 0;"><strong>Removed:</strong> ' . number_format( $removed ) . '</p>';
                echo '<p style="margin: 5px 0;"><strong>New:</strong> ' . number_format( $new ) . '</p>';
                echo '<p style="margin: 5px 0;"><strong>Price Updates:</strong> ' . number_format( $price_updates ) . '</p>';
                echo '<p style="margin: 5px 0;"><strong>Days on Market Updates:</strong> ' . number_format( $days_updates ) . '</p>';
                echo '</div>';
            }
            
            // Estimated time remaining
            if ( isset( $active_progress['timestamp'] ) && $current > 0 ) {
                $time_elapsed = time() - intval( $active_progress['timestamp'] );
                if ( $time_elapsed > 0 && $current > 0 ) {
                    $rate = $current / $time_elapsed; // items per second
                    $remaining = $total - $current;
                    if ( $rate > 0 ) {
                        $eta_seconds = $remaining / $rate;
                        $eta_minutes = round( $eta_seconds / 60, 1 );
                        echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Estimated time remaining:</strong> <span id="yatco-eta-text">' . esc_html( $eta_minutes ) . ' minutes</span></p>';
                    } else {
                        echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Estimated time remaining:</strong> <span id="yatco-eta-text">calculating...</span></p>';
                    }
                } else {
                    echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Estimated time remaining:</strong> <span id="yatco-eta-text">calculating...</span></p>';
                }
            } else {
                echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Estimated time remaining:</strong> <span id="yatco-eta-text">calculating...</span></p>';
            }
            
            // Stop button
            echo '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0;">';
            echo '<form method="post" id="yatco-stop-import-form" style="display: inline-block;">';
            wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
            echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; padding: 8px 16px;">🛑 Stop Import</button>';
            echo '</form>';
            echo '<p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">Click to cancel the current import. The import will stop at the next checkpoint.</p>';
            echo '</div>';
        } elseif ( $cache_status !== false ) {
            echo '<div class="notice notice-info" style="margin: 0;">';
            echo '<p><strong>Status:</strong> ' . esc_html( $cache_status ) . '</p>';
            echo '</div>';
        } else {
            echo '<p style="margin: 0; color: #666;">No active import. Start a full import or daily sync in the Import tab to see progress.</p>';
        }
        
        echo '</div>';
        
        // Handle stop import form submission
        if ( isset( $_POST['yatco_stop_import'] ) && check_admin_referer( 'yatco_stop_import', 'yatco_stop_import_nonce' ) ) {
            yatco_log( 'Import: Stop button clicked (form submission)', 'info' );
            // Set stop flag using WordPress option (more reliable for direct runs)
            update_option( 'yatco_import_stop_flag', time(), false );
            // Also set as transient for backwards compatibility
            set_transient( 'yatco_cache_warming_stop', time(), 900 );
            
            // Cancel any scheduled cron jobs
            $scheduled_full = wp_next_scheduled( 'yatco_full_import_hook' );
            $scheduled_warm = wp_next_scheduled( 'yatco_warm_cache_hook' );
            
            if ( $scheduled_full ) {
                wp_unschedule_event( $scheduled_full, 'yatco_full_import_hook' );
                yatco_log( 'Import: Cancelled scheduled Full Import event', 'info' );
            }
            if ( $scheduled_warm ) {
                wp_unschedule_event( $scheduled_warm, 'yatco_warm_cache_hook' );
                yatco_log( 'Import: Cancelled scheduled warm cache event', 'info' );
            }
            
            wp_clear_scheduled_hook( 'yatco_full_import_hook' );
            wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
            
            // Clear progress and status (but KEEP the stop flag so running import can detect it!)
            delete_transient( 'yatco_import_progress' );
            delete_transient( 'yatco_cache_warming_status' );
            
            // Set status message
            set_transient( 'yatco_cache_warming_status', 'Import stopped by user.', 60 );
            yatco_log( 'Import: Stop signal sent and progress cleared (stop flag remains active)', 'info' );
            
            echo '<div class="notice notice-success" style="margin-top: 15px;"><p><strong>Stop signal sent!</strong> The import will stop at the next checkpoint (within a few seconds). Progress has been reset.</p></div>';
        }
        
        // Status tab - real-time updates using WordPress Heartbeat API
        echo '<script type="text/javascript">';
        echo 'jQuery(document).ready(function($) {';
        echo '    function updateStatusProgress(progressData) {';
        echo '        if (!progressData || !progressData.active) {';
        echo '            return;';
        echo '        }';
        echo '        ';
        echo '        var percent = progressData.percent || 0;';
        echo '        var progressBar = $("#yatco-progress-bar");';
        echo '        if (progressBar.length) {';
        echo '            progressBar.css("width", percent + "%");';
        echo '            progressBar.text(percent.toFixed(1) + "%");';
        echo '        }';
        echo '        ';
        echo '        // Update status text';
        echo '        if (progressData.status && $("#yatco-status-text").length) {';
        echo '            $("#yatco-status-text").html("<strong>Status:</strong> " + progressData.status);';
        echo '        }';
        echo '        ';
        echo '        // Update counts';
        echo '        if (progressData.current !== undefined && $("#yatco-processed-count").length) {';
        echo '            $("#yatco-processed-count").text(progressData.current.toLocaleString() + " / " + progressData.total.toLocaleString());';
        echo '        }';
        echo '        if (progressData.remaining !== undefined && $("#yatco-remaining-count").length) {';
        echo '            $("#yatco-remaining-count").text(progressData.remaining.toLocaleString());';
        echo '        }';
        echo '        ';
        echo '        // Update ETA';
        echo '        if (progressData.eta_minutes !== undefined && $("#yatco-eta-text").length) {';
        echo '            $("#yatco-eta-text").text(progressData.eta_minutes + " minutes");';
        echo '        }';
        echo '        ';
        echo '        if (window.yatcoDebug) {';
        echo '            console.log("YATCO Status Tab Progress Update (Heartbeat):", progressData);';
        echo '        }';
        echo '    }';
        echo '    ';
        echo '    // Listen to heartbeat tick for progress updates';
        echo '    $(document).on("heartbeat-tick", function(event, data) {';
        echo '        if (data.yatco_import_progress) {';
        echo '            updateStatusProgress(data.yatco_import_progress);';
        echo '        }';
        echo '        if (data.yatco_auto_resume) {';
        echo '            console.log("YATCO: Auto-resuming import...");';
        echo '        }';
        echo '    });';
        echo '    ';
        echo '    console.log("YATCO Status Tab: Heartbeat-based progress updates enabled");';
        echo '});';
        echo '</script>';
        
        echo '</div>'; // Close status tab section
    }
    
    // Testing Tab
    if ( $current_tab === 'testing' ) {
        echo '<div class="yatco-testing-section">';
    echo '<h2>Test API Connection</h2>';
    echo '<p>This test calls the <code>/ForSale/vessel/activevesselmlsid</code> endpoint using your Basic token.</p>';
    echo '<form method="post">';
    wp_nonce_field( 'yatco_test_connection', 'yatco_test_connection_nonce' );
    submit_button( 'Test Connection', 'secondary', 'yatco_test_connection' );
    echo '</form>';

    if ( isset( $_POST['yatco_test_connection'] ) && check_admin_referer( 'yatco_test_connection', 'yatco_test_connection_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token.</p></div>';
        } else {
            $result = yatco_test_connection( $token );
            echo $result;
        }
    }

        echo '<hr style="margin: 30px 0;" />';
    echo '<h2>Test Single Vessel & Create Post</h2>';
    echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">';
    echo '<p style="margin: 0; font-weight: bold; color: #856404;"><strong>📝 Test & Import:</strong> This button fetches the first active vessel from the YATCO API, displays its data structure, <strong>and creates a CPT post</strong> so you can preview how the template looks.</p>';
    echo '</div>';
    echo '<p>This is useful for testing the single yacht template before importing all vessels.</p>';
    echo '<form method="post" id="yatco-test-vessel-form">';
    wp_nonce_field( 'yatco_test_vessel', 'yatco_test_vessel_nonce' );
    submit_button( '🔍 Fetch First Vessel & Create Test Post', 'secondary', 'yatco_test_vessel_data_only', false, array( 'id' => 'yatco-test-vessel-btn' ) );
    echo '</form>';

    if ( isset( $_POST['yatco_test_vessel_data_only'] ) && ! empty( $_POST['yatco_test_vessel_data_only'] ) ) {
        if ( ! isset( $_POST['yatco_test_vessel_nonce'] ) || ! wp_verify_nonce( $_POST['yatco_test_vessel_nonce'], 'yatco_test_vessel' ) ) {
            echo '<div class="notice notice-error"><p>Security check failed. Please refresh the page and try again.</p></div>';
        } elseif ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            echo '<div class="notice notice-info" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; margin: 15px 0;">';
            echo '<p><strong>🔍 Test Mode - Fetching & Importing First Vessel</strong></p>';
            echo '<p>This will fetch vessel data from the YATCO API, display it below, and <strong>create a CPT post</strong> so you can see how the template renders.</p>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
            echo '<h3>Step 1: Getting Active Vessel IDs (Read Only)</h3>';
            
            // Get multiple vessel IDs so we can try different ones if the first doesn't have FullSpecsAll data
            $vessel_ids = yatco_get_active_vessel_ids( $token, 10 );
            
            if ( is_wp_error( $vessel_ids ) ) {
                echo '<div class="notice notice-error"><p><strong>Error getting vessel IDs:</strong> ' . esc_html( $vessel_ids->get_error_message() ) . '</p></div>';
                echo '</div>';
            } elseif ( empty( $vessel_ids ) || ! is_array( $vessel_ids ) ) {
                echo '<div class="notice notice-error"><p><strong>Error:</strong> No vessel IDs returned. The API response may be empty or invalid.</p></div>';
                echo '</div>';
            } else {
                echo '<p><strong>✅ Success!</strong> Found ' . count( $vessel_ids ) . ' vessel ID(s). Will try each one until we find one with accessible FullSpecsAll data.</p>';
                
                // Try multiple vessel IDs until we find one with FullSpecsAll data
                $found_vessel_id = null;
                $fullspecs = null;
                $response = null;
                $tried_vessels = array();
                
                foreach ( $vessel_ids as $vessel_id ) {
                    $tried_vessels[] = $vessel_id;
                    echo '<h3>Step 2: Trying Vessel ID ' . esc_html( $vessel_id ) . '</h3>';
                    
                    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll';
                    echo '<p style="color: #666; font-size: 13px;">Endpoint: <code>' . esc_html( $endpoint ) . '</code></p>';
                    
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
                        echo '<p style="color: #dc3232;">❌ WP_Remote Error: ' . esc_html( $response->get_error_message() ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    $response_code = wp_remote_retrieve_response_code( $response );
                    $response_body = wp_remote_retrieve_body( $response );
                    
                    if ( 200 !== $response_code ) {
                        echo '<p style="color: #dc3232;">❌ HTTP Error ' . esc_html( $response_code ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    $fullspecs = json_decode( $response_body, true );
                    $json_error = json_last_error();
                    
                    if ( $json_error !== JSON_ERROR_NONE ) {
                        echo '<p style="color: #dc3232;">❌ JSON Parse Error: ' . esc_html( json_last_error_msg() ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    if ( $fullspecs === null || empty( $fullspecs ) ) {
                        echo '<p style="color: #ff9800;">⚠️ API returned null for vessel ID ' . esc_html( $vessel_id ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    // Found one with data!
                    $found_vessel_id = $vessel_id;
                    echo '<p style="color: #46b450; font-weight: bold;">✅ Found vessel with accessible FullSpecsAll data: Vessel ID ' . esc_html( $found_vessel_id ) . '</p>';
                    
                    if ( count( $tried_vessels ) > 1 ) {
                        echo '<p style="color: #666; font-size: 13px;">Note: Tried ' . count( $tried_vessels ) . ' vessel(s) before finding one with accessible data: ' . esc_html( implode( ', ', array_slice( $tried_vessels, 0, -1 ) ) ) . '</p>';
                    }
                    break;
                }
                
                if ( ! $found_vessel_id || ! $fullspecs ) {
                    echo '<div class="notice notice-error" style="background: #fce8e6; border-left: 4px solid #dc3232; padding: 15px; margin: 20px 0;">';
                    echo '<p style="font-size: 16px; font-weight: bold; margin: 0 0 10px 0;"><strong>❌ No Accessible Vessels Found</strong></p>';
                    echo '<p>Tried ' . count( $tried_vessels ) . ' vessel ID(s): <code>' . esc_html( implode( ', ', $tried_vessels ) ) . '</code></p>';
                    echo '<p>None of these vessels have accessible FullSpecsAll data. This may indicate:</p>';
                    echo '<ul style="margin-left: 20px; margin-top: 10px;">';
                    echo '<li>Your API token may not have permissions to access FullSpecsAll data</li>';
                    echo '<li>The vessels may have been removed or are restricted</li>';
                    echo '<li>There may be a temporary API issue</li>';
                    echo '</ul>';
                    echo '<p style="margin-top: 15px;">Please verify your API token permissions or contact YATCO support.</p>';
                    echo '</div>';
                } else {
                    // Found a vessel with accessible FullSpecsAll data
                    echo '<p><strong>Response Code:</strong> ' . esc_html( wp_remote_retrieve_response_code( $response ) ) . '</p>';
                    echo '<p><strong>Content-Type:</strong> ' . esc_html( wp_remote_retrieve_header( $response, 'content-type' ) ) . '</p>';
                    echo '<p><strong>Response Length:</strong> ' . strlen( wp_remote_retrieve_body( $response ) ) . ' characters</p>';
                    echo '<p><strong>✅ Success!</strong> FullSpecsAll data retrieved and parsed successfully.</p>';
                    
                    if ( is_array( $fullspecs ) && ! empty( $fullspecs ) ) {
                        $sections_found = array_keys( $fullspecs );
                        echo '<p style="color: #666; font-size: 13px;">Data sections found: <strong>' . esc_html( count( $sections_found ) ) . '</strong> sections (' . esc_html( implode( ', ', array_slice( $sections_found, 0, 5 ) ) ) . ( count( $sections_found ) > 5 ? ', ...' : '' ) . ')</p>';
                    }
                    
                    echo '<h3>Step 3: Importing Vessel to CPT</h3>';
                    echo '<p>Now importing vessel ID ' . esc_html( $found_vessel_id ) . ' data into your Custom Post Type...</p>';
                    
                    // Flush output so user sees the message
                    if ( ob_get_level() > 0 ) {
                        ob_flush();
                    }
                    flush();
                    
                    require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
                    
                    // Clear any stop flag that might be lingering from previous imports
                    delete_option( 'yatco_import_stop_flag' );
                    delete_transient( 'yatco_cache_warming_stop' );
                    
                    // Increase execution time for test
                    @set_time_limit( 120 );
                    @ini_set( 'max_execution_time', 120 );
                    
                    echo '<p style="color: #666; font-size: 13px;">Calling yatco_import_single_vessel()...</p>';
                    
                    // Flush again
                    if ( ob_get_level() > 0 ) {
                        ob_flush();
                    }
                    flush();
                    
                    $import_result = yatco_import_single_vessel( $token, $found_vessel_id );
                    
                    if ( is_wp_error( $import_result ) ) {
                        echo '<div class="notice notice-error">';
                        echo '<p><strong>❌ Import Failed:</strong> ' . esc_html( $import_result->get_error_message() ) . '</p>';
                        echo '<p><strong>Error Code:</strong> ' . esc_html( $import_result->get_error_code() ) . '</p>';
                        if ( $import_result->get_error_code() === 'import_stopped' ) {
                            echo '<p style="color: #dc3232;"><strong>Note:</strong> The import was stopped because a stop flag was detected. This might be from a previous import. Try clearing the stop flag and running the test again.</p>';
                        } else {
                        echo '<p>This might be due to missing or invalid data in the API response. Check the raw JSON below for details.</p>';
                        }
                        echo '</div>';
                    } elseif ( empty( $import_result ) ) {
                        echo '<div class="notice notice-error">';
                        echo '<p><strong>❌ Import Failed:</strong> The import function returned an empty result. This might indicate a fatal error occurred.</p>';
                        echo '<p>Please check your PHP error logs for more details.</p>';
                        echo '</div>';
                    } else {
                        $post_id = $import_result;
                        $post_title = get_the_title( $post_id );
                        $post_permalink = get_permalink( $post_id );
                        
                        echo '<div class="notice notice-success" style="background: #d4edda; border-left: 4px solid #46b450; padding: 15px; margin: 20px 0;">';
                        echo '<p style="font-size: 16px; font-weight: bold; margin: 0 0 10px 0;"><strong>✅ Vessel Imported Successfully!</strong></p>';
                        echo '<p style="margin: 5px 0;"><strong>Post ID:</strong> ' . esc_html( $post_id ) . '</p>';
                        echo '<p style="margin: 5px 0;"><strong>Title:</strong> ' . esc_html( $post_title ) . '</p>';
                        echo '<p style="margin: 15px 0 5px 0;"><strong>View the post:</strong></p>';
                        echo '<p style="margin: 5px 0;">';
                        echo '<a href="' . esc_url( $post_permalink ) . '" target="_blank" class="button button-primary" style="margin-right: 10px; display: inline-block; padding: 8px 16px; text-decoration: none; background: #2271b1; color: #fff; border-radius: 3px; font-weight: bold;">👁️ View Post (New Tab)</a>';
                        echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '" class="button button-secondary" style="display: inline-block; padding: 8px 16px; text-decoration: none; background: #f0f0f1; color: #2c3338; border-radius: 3px; border: 1px solid #8c8f94;">✏️ Edit Post</a>';
                        echo '</p>';
                        echo '</div>';
                        
                        echo '<h4 style="margin-top: 20px;">Import Summary:</h4>';
                        echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">';
                        echo '<ul style="list-style: disc; margin-left: 20px;">';
                        
                        $meta_fields_to_check = array(
                            'yacht_vessel_id' => 'Vessel ID',
                            'yacht_year' => 'Year',
                            'yacht_make' => 'Builder',
                            'yacht_model' => 'Model',
                            'yacht_price' => 'Price',
                            'yacht_length' => 'Length',
                            'yacht_location_custom_rjc' => 'Location',
                        );
                        
                        foreach ( $meta_fields_to_check as $meta_key => $label ) {
                            $meta_value = get_post_meta( $post_id, $meta_key, true );
                            if ( ! empty( $meta_value ) ) {
                                echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $meta_value ) . '</li>';
                            }
                        }
                        
                        $gallery_count = 0;
                        $gallery_urls = get_post_meta( $post_id, 'yacht_image_gallery_urls', true );
                        if ( is_array( $gallery_urls ) ) {
                            $gallery_count = count( $gallery_urls );
                        }
                        if ( $gallery_count > 0 ) {
                            echo '<li><strong>Gallery Images:</strong> ' . esc_html( $gallery_count ) . ' images</li>';
                        }
                        
                        echo '</ul>';
                        echo '</div>';
                            
                            // Check for price reduction fields
                            echo '<h3 style="margin-top: 30px;">Price Reduction Check</h3>';
                            echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">';
                            echo '<p><strong>Checking for price reduction fields in API response...</strong></p>';
                            
                            $price_reduction_fields = array();
                            $price_fields_found = array();
                            
                            // Search for price-related fields
                            $search_terms = array( 'reduction', 'original', 'previous', 'old', 'was', 'before', 'discount', 'savings', 'decrease' );
                            
                            // Recursive function to search for fields
                            $search_in_array = function( $array, $prefix = '' ) use ( &$search_in_array, &$price_fields_found, $search_terms ) {
                                foreach ( $array as $key => $value ) {
                                    $full_key = $prefix ? $prefix . '.' . $key : $key;
                                    $key_lower = strtolower( $key );
                                    
                                    // Check if key contains price-related terms
                                    foreach ( $search_terms as $term ) {
                                        if ( strpos( $key_lower, $term ) !== false ) {
                                            $price_fields_found[ $full_key ] = $value;
                                        }
                                    }
                                    
                                    // Also collect all price-related fields
                                    if ( strpos( $key_lower, 'price' ) !== false ) {
                                        $price_fields_found[ $full_key ] = $value;
                                    }
                                    
                                    // Recursively search nested arrays
                                    if ( is_array( $value ) ) {
                                        $search_in_array( $value, $full_key );
                                    }
                                }
                            };
                            
                            $search_in_array( $fullspecs );
                            
                            if ( ! empty( $price_fields_found ) ) {
                                echo '<p style="color: #46b450; font-weight: bold;">✅ Found ' . count( $price_fields_found ) . ' price-related field(s):</p>';
                                echo '<table class="widefat" style="margin-top: 10px;">';
                                echo '<thead><tr><th style="width: 300px;">Field Name</th><th>Value</th></tr></thead>';
                                echo '<tbody>';
                                foreach ( $price_fields_found as $field => $value ) {
                                    $is_reduction = false;
                                    foreach ( $search_terms as $term ) {
                                        if ( stripos( $field, $term ) !== false ) {
                                            $is_reduction = true;
                                            break;
                                        }
                                    }
                                    $row_class = $is_reduction ? 'style="background: #fff3cd;"' : '';
                                    echo '<tr ' . $row_class . '>';
                                    echo '<td><code>' . esc_html( $field ) . '</code></td>';
                                    if ( is_array( $value ) ) {
                                        echo '<td><pre style="margin: 0; font-size: 11px;">' . esc_html( wp_json_encode( $value, JSON_PRETTY_PRINT ) ) . '</pre></td>';
    } else {
                                        echo '<td>' . esc_html( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value ) . '</td>';
                                    }
                                    echo '</tr>';
                                }
                                echo '</tbody>';
                                echo '</table>';
                                
                                // Highlight reduction-related fields
                                $reduction_count = 0;
                                foreach ( $price_fields_found as $field => $value ) {
                                    foreach ( $search_terms as $term ) {
                                        if ( stripos( $field, $term ) !== false ) {
                                            $reduction_count++;
                        break;
                                        }
                                    }
                                }
                                
                                if ( $reduction_count > 0 ) {
                                    echo '<div style="background: #d4edda; border-left: 4px solid #46b450; padding: 12px; margin-top: 15px;">';
                                    echo '<p style="margin: 0; font-weight: bold; color: #155724;">🎉 Found ' . $reduction_count . ' potential price reduction field(s)!</p>';
                                    echo '<p style="margin: 5px 0 0 0;">These fields (highlighted in yellow above) may contain price reduction information.</p>';
        echo '</div>';
        } else {
                                    echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 15px;">';
                                    echo '<p style="margin: 0;"><strong>Note:</strong> No obvious price reduction fields found, but all price-related fields are listed above.</p>';
            echo '</div>';
                                }
    } else {
                                echo '<p style="color: #dc3232;">❌ No price-related fields found in API response.</p>';
                            }
                echo '</div>';
                    
                    echo '<h3 style="margin-top: 30px;">Raw API Response Data Structure</h3>';
                            echo '<p style="color: #666; font-size: 13px;">Below is the complete JSON response from the YATCO API for reference. You can search for "reduction", "original", "previous", or "price" to find relevant fields:</p>';
                    
                    echo '<div style="background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 15px; max-height: 400px; overflow: auto; font-family: monospace; font-size: 11px; line-height: 1.4;">';
                    echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">';
                    echo esc_html( wp_json_encode( $fullspecs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
                    echo '</pre>';
            echo '</div>';
                }
            echo '</div>';
                    }
                }
            }
        }
        
        echo '</div>'; // Close yatco-testing-section
    }
    
    // Troubleshooting Tab
    if ( $current_tab === 'troubleshooting' ) {
        echo '<div class="yatco-troubleshooting-section">';
        echo '<h2>Troubleshooting</h2>';
        echo '<p>This section contains diagnostic tools and information to help troubleshoot issues.</p>';
    echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    
    $cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    echo '<h3>WP-Cron Status</h3>';
    echo '<table class="widefat" style="margin-bottom: 20px;">';
    echo '<tr><th style="text-align: left; width: 250px;">Check</th><th style="text-align: left;">Status</th></tr>';
    
    echo '<tr><td><strong>WP-Cron Enabled:</strong></td><td>';
    if ( $cron_disabled ) {
        echo '<span style="color: #dc3232;">❌ DISABLED</span>';
    } else {
        echo '<span style="color: #46b450;">✔ ENABLED</span>';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>spawn_cron() Available:</strong></td><td>';
    if ( function_exists( 'spawn_cron' ) ) {
        echo '<span style="color: #46b450;">✔ Available</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Available</span>';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>Cache Warming Function:</strong></td><td>';
    if ( function_exists( 'yatco_warm_cache_function' ) ) {
        echo '<span style="color: #46b450;">✔ Available</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Available</span>';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>Hooks Registered:</strong></td><td>';
    $hooks_registered = array();
    if ( isset( $wp_filter['yatco_warm_cache_hook'] ) ) {
        $hooks_registered[] = 'yatco_warm_cache_hook';
    }
    if ( isset( $wp_filter['yatco_full_import_hook'] ) ) {
        $hooks_registered[] = 'yatco_full_import_hook';
    }
    if ( isset( $wp_filter['yatco_daily_sync_hook'] ) ) {
        $hooks_registered[] = 'yatco_daily_sync_hook';
    }
    if ( ! empty( $hooks_registered ) ) {
        echo '<span style="color: #46b450;">✔ Registered: ' . esc_html( implode( ', ', $hooks_registered ) ) . '</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ No hooks registered</span>';
    }
    echo '</td></tr>';
    
    $scheduled_full = wp_next_scheduled( 'yatco_full_import_hook' );
    $scheduled_sync = wp_next_scheduled( 'yatco_daily_sync_hook' );
    $scheduled_warm = wp_next_scheduled( 'yatco_warm_cache_hook' );
    echo '<tr><td><strong>Scheduled Events:</strong></td><td>';
    $scheduled_events = array();
    if ( $scheduled_full ) {
        $scheduled_events[] = 'Full Import: ' . date( 'Y-m-d H:i:s', $scheduled_full );
    }
    if ( $scheduled_sync ) {
        $scheduled_events[] = 'Daily Sync: ' . date( 'Y-m-d H:i:s', $scheduled_sync );
    }
    if ( $scheduled_warm ) {
        $scheduled_events[] = 'Warm Cache: ' . date( 'Y-m-d H:i:s', $scheduled_warm );
    }
    if ( ! empty( $scheduled_events ) ) {
        echo '<span style="color: #ff9800;">⚠ ' . esc_html( implode( '<br />', $scheduled_events ) ) . '</span>';
    } else {
        echo 'None scheduled';
    }
    echo '</td></tr>';
    
        $cache_status = get_transient( 'yatco_cache_warming_status' );
    echo '<tr><td><strong>Last Status:</strong></td><td>';
    if ( $cache_status !== false ) {
        echo esc_html( $cache_status );
    } else {
        echo 'No status recorded';
    }
    echo '</td></tr>';
    
        $import_progress = get_transient( 'yatco_import_progress' );
    echo '<tr><td><strong>Last Progress:</strong></td><td>';
    if ( $import_progress !== false && is_array( $import_progress ) ) {
        $last_processed = isset( $import_progress['last_processed'] ) ? intval( $import_progress['last_processed'] ) : 0;
        $total = isset( $import_progress['total'] ) ? intval( $import_progress['total'] ) : 0;
        echo number_format( $last_processed ) . ' / ' . number_format( $total );
    } else {
        echo 'No progress recorded';
    }
    echo '</td></tr>';
    
    echo '</table>';
    
    echo '<h3>Manual Testing</h3>';
    echo '<p>Use these buttons to test if the system is working:</p>';
    
    echo '<form method="post" style="margin-bottom: 15px;">';
    wp_nonce_field( 'yatco_test_function', 'yatco_test_function_nonce' );
    submit_button( 'Test Function & API Connection', 'secondary', 'yatco_test_function' );
    echo '</form>';
    
    if ( isset( $_POST['yatco_test_function'] ) && check_admin_referer( 'yatco_test_function', 'yatco_test_function_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token.</p></div>';
        } else {
            if ( function_exists( 'yatco_warm_cache_function' ) ) {
                echo '<div class="notice notice-success"><p>✅ Cache warming function is available.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Cache warming function is NOT available. Check if yatco-cache.php is loaded.</p></div>';
            }
            
            if ( isset( $wp_filter['yatco_warm_cache_hook'] ) ) {
                echo '<div class="notice notice-success"><p>✅ Hook is registered.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Hook is NOT registered.</p></div>';
            }
            
            $scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
            if ( $scheduled ) {
                echo '<div class="notice notice-info"><p>⚠️ A scheduled event exists for ' . date( 'Y-m-d H:i:s', $scheduled ) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>ℹ️ No scheduled events found.</p></div>';
            }
            
            if ( function_exists( 'yatco_get_active_vessel_ids' ) ) {
                $test_token = $token;
                $test_ids = yatco_get_active_vessel_ids( $test_token, 5 );
                if ( is_wp_error( $test_ids ) ) {
                    echo '<div class="notice notice-error"><p>❌ Error fetching vessel IDs: ' . esc_html( $test_ids->get_error_message() ) . '</p></div>';
                } elseif ( empty( $test_ids ) ) {
                    echo '<div class="notice notice-warning"><p>⚠️ No vessel IDs returned.</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>✅ Successfully fetched ' . count( $test_ids ) . ' vessel ID(s).</p></div>';
                }
            }
        }
    }
    
    echo '<form method="post" style="margin-bottom: 15px;">';
    wp_nonce_field( 'yatco_test_cron', 'yatco_test_cron_nonce' );
    submit_button( 'Test WP-Cron', 'secondary', 'yatco_test_cron' );
    echo '</form>';
    
    if ( isset( $_POST['yatco_test_cron'] ) && check_admin_referer( 'yatco_test_cron', 'yatco_test_cron_nonce' ) ) {
        $test_key = 'yatco_test_cron_' . time();
        set_transient( $test_key, 'not_run', 60 );
        
        wp_schedule_single_event( time(), 'yatco_test_cron_hook' );
        
        add_action( 'yatco_test_cron_hook', function() use ( $test_key ) {
            set_transient( $test_key, 'ran_successfully', 60 );
        } );
        
        echo '<p>Testing WP-Cron...</p>';
        echo '<div style="background: #fff; border: 1px solid #ddd; padding: 10px; font-family: monospace; font-size: 12px;">';
        
        if ( function_exists( 'spawn_cron' ) ) {
            echo '✅ spawn_cron() Available<br />';
            spawn_cron();
            echo '✅ spawn_cron() called<br />';
        } else {
            echo '❌ spawn_cron() NOT Available<br />';
        }
        
        echo '🔄 Attempting to trigger wp-cron.php directly...<br />';
        
        $response = wp_remote_post(
            site_url( 'wp-cron.php?doing_wp_cron' ),
            array(
                'timeout'   => 0.01,
                'blocking'  => false,
                'sslverify' => false,
            )
        );
        
        if ( is_wp_error( $response ) ) {
            echo '⚠️ wp-cron.php request failed: ' . esc_html( $response->get_error_message() ) . '<br />';
        } else {
            echo '✅ wp-cron.php request sent (non-blocking)<br />';
        }
        
        echo '<br />⏳ Checking if cron ran (waiting up to 8 seconds)...<br />';
        $test_result = false;
        for ( $i = 0; $i < 8; $i++ ) {
            sleep( 1 );
            $check = get_transient( $test_key );
            if ( $check === 'ran_successfully' ) {
                $test_result = true;
                echo '✓ Checked at ' . ( $i + 1 ) . ' seconds: <span style="color: #46b450;">CRON RAN!</span><br />';
                break;
            } elseif ( $check !== 'not_run' ) {
                echo '⚠️ Checked at ' . ( $i + 1 ) . ' seconds: Transient changed but value unexpected: ' . esc_html( $check ) . '<br />';
                break;
            } else {
                echo '• Checked at ' . ( $i + 1 ) . ' seconds: Still waiting...<br />';
            }
        }
        
        echo '<br />';
        if ( $test_result === true ) {
            echo '<span style="color: #46b450; font-weight: bold; font-size: 14px;">✅ SUCCESS! WP-Cron is working! The test hook ran successfully.</span><br />';
            echo '<p style="margin: 5px 0; color: #666; font-size: 12px;">Your WP-Cron is functioning correctly. The cache warming should work with the "Warm Cache" button.</p>';
        } elseif ( $test_result === false ) {
            echo '<span style="color: #dc3232; font-weight: bold; font-size: 14px;">❌ FAILED! WP-Cron did not run. The test hook did not execute.</span><br />';
            echo '<p style="margin: 5px 0; color: #666; font-size: 12px;">This means WP-Cron is likely disabled or not working on your server. You should use the "Run Directly" button or "Run Cache Warming Function NOW (Direct)" instead.</p>';
            echo '<p style="margin: 5px 0; color: #666; font-size: 12px;"><strong>Solution:</strong> Set up a real cron job on your server to call <code>wp-cron.php</code> every 5-15 minutes, or use the "Run Directly" button for manual imports.</p>';
        }
        
        echo '</div>';
        
        delete_transient( $test_key );
    }
    
    echo '<h3>Manual Cache Warming (Direct)</h3>';
    echo '<p>If WP-Cron is not working, you can run the cache warming function directly. This will block until complete.</p>';
    
    echo '<form method="post" style="margin-bottom: 15px;">';
    wp_nonce_field( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' );
    submit_button( 'Run Cache Warming Function NOW (Direct)', 'primary large', 'yatco_manual_trigger', false, array( 'style' => 'font-size: 14px; padding: 8px 16px; height: auto;' ) );
    echo '</form>';
    
    if ( isset( $_POST['yatco_manual_trigger'] ) && check_admin_referer( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token.</p></div>';
        } else {
            if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';
            }
            
            set_transient( 'yatco_cache_warming_status', 'Starting direct cache warm-up...', 600 );
            set_transient( 'yatco_cache_warming_progress', array( 'last_processed' => 0, 'total' => 0, 'timestamp' => time() ), 600 );
            
            echo '<div class="notice notice-info">';
            echo '<p><strong>Starting direct cache warming...</strong></p>';
            echo '<p>This will run synchronously (blocking) and may take several minutes. <strong>Do not close this page.</strong></p>';
            echo '</div>';
            
            ob_flush();
            flush();
            
            $is_running = true;
            
            echo '<form method="post" style="margin: 10px 0;">';
            wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
            echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; padding: 8px 16px;">🛑 Stop Import Now</button>';
            echo '</form>';
            
            yatco_warm_cache_function();
            
            $final_status = get_transient( 'yatco_cache_warming_status' );
            $final_progress = get_transient( 'yatco_cache_warming_progress' );
            
            echo '<div class="notice notice-success">';
            echo '<p><strong>Direct cache warming completed!</strong></p>';
            if ( $final_progress !== false && is_array( $final_progress ) ) {
                $processed = isset( $final_progress['processed'] ) ? intval( $final_progress['processed'] ) : 0;
                echo '<p>Total vessels processed: <strong>' . number_format( $processed ) . '</strong></p>';
            }
            echo '</div>';
            
            $is_running = false;
        }
    }
    
    echo '<h3>Real Cron Setup (If WP-Cron Disabled)</h3>';
    echo '<div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 10px 0;">';
    echo '<p>If WP-Cron is disabled on your server, you can set up a real cron job to trigger the cache warming automatically.</p>';
    echo '<p><strong>Option 1: Using curl</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/15 * * * * curl -s ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</pre>';
    echo '<p><strong>Option 2: Using wget</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/15 * * * * wget -q -O - ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</pre>';
    echo '<p><strong>Option 3: Using wp-cli (if available)</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/15 * * * * cd ' . esc_html( ABSPATH ) . ' && wp cron event run --due-now</pre>';
    echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">Note: Replace <code>*/15</code> with your desired frequency (e.g., <code>*/5</code> for every 5 minutes). Add this to your server\'s crontab using <code>crontab -e</code>.</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>'; // Close troubleshooting section
    }
    
    echo '</div>'; // End tab content
    echo '</div>'; // End wrap
    
    // Add some basic CSS for tabs
    echo '<style>
    .yatco-tab-content {
        background: #fff;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-top: none;
    }
    .yatco-settings-section,
    .yatco-import-section,
    .yatco-testing-section,
    .yatco-status-section,
    .yatco-troubleshooting-section {
        max-width: 1200px;
    }
    .yatco-settings-section h2,
    .yatco-import-section h2,
    .yatco-testing-section h2,
    .yatco-status-section h2,
    .yatco-troubleshooting-section h2 {
        margin-top: 0;
        padding-top: 0;
    }
    </style>';
}

/**
 * AJAX handler to manually trigger cache warming.
 */
function yatco_ajax_trigger_cache_warming() {
    check_ajax_referer( 'yatco_trigger_warming', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    delete_transient( 'yatco_cache_warming_progress' );
    delete_transient( 'yatco_cache_warming_status' );
    
    set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
    
    wp_schedule_single_event( time(), 'yatco_warm_cache_hook' );
    spawn_cron();
    
    wp_send_json_success( array( 'message' => 'Cache warming started' ) );
}
add_action( 'wp_ajax_yatco_trigger_cache_warming', 'yatco_ajax_trigger_cache_warming' );

/**
 * AJAX handler to run cache warming directly (synchronous - for testing).
 */
function yatco_ajax_run_cache_warming_direct() {
    check_ajax_referer( 'yatco_run_warming_direct', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
        require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';
    }
    
    set_transient( 'yatco_cache_warming_status', 'Starting direct cache warm-up...', 600 );
    yatco_warm_cache_function();
    
    wp_send_json_success( array( 'message' => 'Direct cache warming completed' ) );
}
add_action( 'wp_ajax_yatco_run_cache_warming_direct', 'yatco_ajax_run_cache_warming_direct' );

/**
 * AJAX handler to get cache warming status and progress.
 */
function yatco_ajax_get_cache_status() {
    check_ajax_referer( 'yatco_get_status', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    $response = array();
    
    $status = get_transient( 'yatco_cache_warming_status' );
    if ( $status !== false ) {
        $response['status'] = $status;
    }
    
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    if ( $cache_progress !== false && is_array( $cache_progress ) ) {
        $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
        $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
        $start_time = isset( $cache_progress['start_time'] ) ? intval( $cache_progress['start_time'] ) : time();
        $current_time = time();
        $elapsed = $current_time - $start_time;
        
        $response['progress'] = array(
            'last_processed' => $last_processed,
            'total'         => $total,
            'processed'     => $cache_progress['processed'] ?? 0,
            'percent'       => $total > 0 ? round( ( $last_processed / $total ) * 100, 1 ) : 0,
        );
        
        if ( $elapsed > 0 && $last_processed > 0 ) {
            $rate = $last_processed / $elapsed;
            $remaining = $total - $last_processed;
            $avg_time_per_vessel = $elapsed / $last_processed;
            $estimated_remaining = $remaining * $avg_time_per_vessel;
            
            $response['progress']['rate'] = number_format( $rate, 1 );
            $response['progress']['elapsed'] = human_time_diff( $start_time, $current_time );
            $response['progress']['estimated_remaining'] = $estimated_remaining > 0 ? human_time_diff( $current_time, $current_time + $estimated_remaining ) : 'calculating...';
        }
    }
    
    wp_send_json_success( $response );
}
add_action( 'wp_ajax_yatco_get_cache_status', 'yatco_ajax_get_cache_status' );

/**
 * Import page (Yachts → YATCO Import).
 */
function yatco_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $token = yatco_get_token();
    echo '<div class="wrap"><h1>YATCO Import</h1>';

    if ( empty( $token ) ) {
        echo '<div class="notice notice-error"><p>Please set your Basic token in <a href="' . esc_url( admin_url( 'options-general.php?page=yatco_api' ) ) . '">Settings → YATCO API</a> first.</p></div>';
        echo '</div>';
        return;
    }

    // Parse criteria - treat empty strings and 0 as "no filter"
    $criteria_price_min = isset( $_POST['price_min'] ) && $_POST['price_min'] !== '' && $_POST['price_min'] !== '0' ? floatval( $_POST['price_min'] ) : '';
    $criteria_price_max = isset( $_POST['price_max'] ) && $_POST['price_max'] !== '' && $_POST['price_max'] !== '0' ? floatval( $_POST['price_max'] ) : '';
    $criteria_year_min  = isset( $_POST['year_min'] ) && $_POST['year_min'] !== '' && $_POST['year_min'] !== '0' ? intval( $_POST['year_min'] ) : '';
    $criteria_year_max  = isset( $_POST['year_max'] ) && $_POST['year_max'] !== '' && $_POST['year_max'] !== '0' ? intval( $_POST['year_max'] ) : '';
    $criteria_loa_min   = isset( $_POST['loa_min'] ) && $_POST['loa_min'] !== '' && $_POST['loa_min'] !== '0' ? floatval( $_POST['loa_min'] ) : '';
    $criteria_loa_max   = isset( $_POST['loa_max'] ) && $_POST['loa_max'] !== '' && $_POST['loa_max'] !== '0' ? floatval( $_POST['loa_max'] ) : '';
    $max_records        = isset( $_POST['max_records'] ) && $_POST['max_records'] !== '' && $_POST['max_records'] > 0 ? intval( $_POST['max_records'] ) : 50;

    $preview_results = array();
    $message         = '';

    // Handle import action.
    if ( isset( $_POST['yatco_import_selected'] ) && ! empty( $_POST['vessel_ids'] ) && is_array( $_POST['vessel_ids'] ) && check_admin_referer( 'yatco_import_action', 'yatco_import_nonce' ) ) {
        $imported = 0;
        foreach ( $_POST['vessel_ids'] as $vessel_id ) {
            $vessel_id = intval( $vessel_id );
            if ( $vessel_id <= 0 ) {
                continue;
            }
            $result = yatco_import_single_vessel( $token, $vessel_id );
            if ( ! is_wp_error( $result ) ) {
                $imported++;
            }
        }
        $message = sprintf( '%d vessel(s) imported/updated.', $imported );
        echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
    }

    // Handle preview action.
    if ( isset( $_POST['yatco_preview_listings'] ) && check_admin_referer( 'yatco_import_action', 'yatco_import_nonce' ) ) {

        // Fetch more IDs than needed to account for filtering (5x the desired results, max 100)
        $ids_to_fetch = min( $max_records * 5, 100 );
        $ids = yatco_get_active_vessel_ids( $token, $ids_to_fetch );

        if ( is_wp_error( $ids ) ) {
            echo '<div class="notice notice-error"><p>Error fetching active vessel IDs: ' . esc_html( $ids->get_error_message() ) . '</p></div>';
        } elseif ( empty( $ids ) ) {
            echo '<div class="notice notice-warning"><p>No active vessels returned from YATCO.</p></div>';
        } else {
            foreach ( $ids as $id ) {
                // Stop if we've reached the desired number of results
                if ( count( $preview_results ) >= $max_records ) {
                    break;
                }

                $full = yatco_fetch_fullspecs( $token, $id );
                if ( is_wp_error( $full ) ) {
                    continue;
                }

                $brief = yatco_build_brief_from_fullspecs( $id, $full );

                // Apply basic filtering in PHP based on criteria.
                $price = ! empty( $brief['Price'] ) ? floatval( $brief['Price'] ) : null;
                $year  = ! empty( $brief['Year'] ) ? intval( $brief['Year'] ) : null;
                // LOA might be a formatted string, extract numeric value.
                $loa_raw = $brief['LOA'];
                if ( is_string( $loa_raw ) && preg_match( '/([0-9.]+)/', $loa_raw, $matches ) ) {
                    $loa = floatval( $matches[1] );
                } elseif ( ! empty( $loa_raw ) && is_numeric( $loa_raw ) ) {
                    $loa = floatval( $loa_raw );
                } else {
                    $loa = null;
                }

                // Apply filters only if criteria are set (not empty string).
                // Skip vessels with null/0 values only if a filter is set.
                if ( $criteria_price_min !== '' ) {
                    if ( is_null( $price ) || $price <= 0 || $price < $criteria_price_min ) {
                        continue;
                    }
                }
                if ( $criteria_price_max !== '' ) {
                    if ( is_null( $price ) || $price <= 0 || $price > $criteria_price_max ) {
                        continue;
                    }
                }
                if ( $criteria_year_min !== '' ) {
                    if ( is_null( $year ) || $year <= 0 || $year < $criteria_year_min ) {
                        continue;
                    }
                }
                if ( $criteria_year_max !== '' ) {
                    if ( is_null( $year ) || $year <= 0 || $year > $criteria_year_max ) {
                        continue;
                    }
                }
                if ( $criteria_loa_min !== '' ) {
                    if ( is_null( $loa ) || $loa <= 0 || $loa < $criteria_loa_min ) {
                        continue;
                    }
                }
                if ( $criteria_loa_max !== '' ) {
                    if ( is_null( $loa ) || $loa <= 0 || $loa > $criteria_loa_max ) {
                        continue;
                    }
                }

                $preview_results[] = $brief;
            }

            if ( empty( $preview_results ) ) {
                echo '<div class="notice notice-warning"><p>No vessels matched your criteria after filtering FullSpecsAll data.</p></div>';
            } elseif ( count( $preview_results ) < $max_records ) {
                echo '<div class="notice notice-info"><p>Found ' . count( $preview_results ) . ' vessel(s) matching your criteria (requested up to ' . $max_records . ').</p></div>';
            }
        }
    }

    ?>
    <h2>Import Criteria</h2>
    <form method="post">
        <?php wp_nonce_field( 'yatco_import_action', 'yatco_import_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row">Price (USD)</th>
                <td>
                    Min: <input type="number" step="1" name="price_min" value="<?php echo $criteria_price_min !== '' ? esc_attr( $criteria_price_min ) : ''; ?>" />
                    Max: <input type="number" step="1" name="price_max" value="<?php echo $criteria_price_max !== '' ? esc_attr( $criteria_price_max ) : ''; ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">Length (LOA)</th>
                <td>
                    Min: <input type="number" step="0.1" name="loa_min" value="<?php echo $criteria_loa_min !== '' ? esc_attr( $criteria_loa_min ) : ''; ?>" />
                    Max: <input type="number" step="0.1" name="loa_max" value="<?php echo $criteria_loa_max !== '' ? esc_attr( $criteria_loa_max ) : ''; ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">Year Built</th>
                <td>
                    Min: <input type="number" step="1" name="year_min" value="<?php echo $criteria_year_min !== '' ? esc_attr( $criteria_year_min ) : ''; ?>" />
                    Max: <input type="number" step="1" name="year_max" value="<?php echo $criteria_year_max !== '' ? esc_attr( $criteria_year_max ) : ''; ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">Max Results</th>
                <td>
                    <input type="number" step="1" name="max_records" value="<?php echo esc_attr( $max_records ); ?>" />
                    <p class="description">Maximum number of matching vessels to display (default 50). The system will fetch up to 5x this number to find matches.</p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Preview Listings', 'primary', 'yatco_preview_listings' ); ?>

        <?php if ( ! empty( $preview_results ) ) : ?>
            <h2>Preview Results</h2>
            <p>Select the vessels you want to import or update.</p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column"><input type="checkbox" onclick="jQuery('.yatco-vessel-checkbox').prop('checked', this.checked);" /></th>
                        <th>Vessel ID</th>
                        <th>MLS ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Year</th>
                        <th>LOA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $preview_results as $row ) : ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input class="yatco-vessel-checkbox" type="checkbox" name="vessel_ids[]" value="<?php echo esc_attr( $row['VesselID'] ); ?>" />
                            </th>
                            <td><?php echo esc_html( $row['VesselID'] ); ?></td>
                            <td><?php echo esc_html( $row['MLSId'] ); ?></td>
                            <td><?php echo esc_html( $row['Name'] ); ?></td>
                            <td><?php echo esc_html( $row['Price'] ); ?></td>
                            <td><?php echo esc_html( $row['Year'] ); ?></td>
                            <td><?php echo esc_html( $row['LOA'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button( 'Import Selected', 'primary', 'yatco_import_selected' ); ?>
        <?php endif; ?>
    </form>
    <?php
    echo '</div>';
}

/**
 * Add meta box to yacht edit screen with Update Vessel button.
 */
function yatco_add_update_vessel_meta_box() {
    add_meta_box(
        'yatco_update_vessel',
        'YATCO Vessel Update',
        'yatco_update_vessel_meta_box_callback',
        'yacht',
        'side',
        'high'
    );
}

/**
 * Meta box callback - displays Update Vessel button.
 */
function yatco_update_vessel_meta_box_callback( $post ) {
    // Check if this is a YATCO vessel
    $vessel_id = get_post_meta( $post->ID, 'yacht_vessel_id', true );
    $mlsid = get_post_meta( $post->ID, 'yacht_mlsid', true );
    
    if ( empty( $vessel_id ) ) {
        echo '<p>This yacht post does not have a YATCO vessel ID. It may not have been imported from YATCO.</p>';
        return;
    }
    
    // Get YATCO listing URL from stored meta, or build it if not stored
    $yatco_listing_url = get_post_meta( $post->ID, 'yacht_yatco_listing_url', true );
    
    // Check if URL is in old format (just ID, not full slug)
    // Old format: https://www.yatco.com/yacht/444215/
    // New format: https://www.yatco.com/yacht/70-rizzardi-motor-yacht-2026-407649/
    $needs_regeneration = false;
    if ( empty( $yatco_listing_url ) ) {
        $needs_regeneration = true;
    } else {
        // Check if URL matches old format (just number before the trailing slash, no hyphens)
        // URLs with hyphens are the new format
        if ( preg_match( '#https?://www\.yatco\.com/yacht/(\d+)/?$#', $yatco_listing_url, $matches ) ) {
            $needs_regeneration = true;
        } elseif ( strpos( $yatco_listing_url, '-' ) === false ) {
            // URL exists but has no hyphens, likely old format
            $needs_regeneration = true;
        }
    }
    
    if ( $needs_regeneration ) {
        // Build YATCO listing URL using helper function
        $length = get_post_meta( $post->ID, 'yacht_length_feet', true );
        $builder = get_post_meta( $post->ID, 'yacht_make', true );
        $category = get_post_meta( $post->ID, 'yacht_sub_category', true );
        if ( empty( $category ) ) {
            $category = get_post_meta( $post->ID, 'yacht_category', true );
        }
        if ( empty( $category ) ) {
            $category = get_post_meta( $post->ID, 'yacht_type', true );
        }
        $year = get_post_meta( $post->ID, 'yacht_year', true );
        
        if ( ! function_exists( 'yatco_build_listing_url' ) ) {
            require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
        }
        
        $yatco_listing_url = yatco_build_listing_url( $post->ID, $mlsid, $vessel_id, $length, $builder, $category, $year );
        // Save it for future use
        if ( ! empty( $yatco_listing_url ) ) {
            update_post_meta( $post->ID, 'yacht_yatco_listing_url', $yatco_listing_url );
        }
    }
    
    // Display link to original YATCO listing - make it very visible
    echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
    echo '<p style="margin: 0 0 12px 0; font-weight: bold; font-size: 14px; color: #2271b1;">🔗 View Original Listing on YATCO</p>';
    
    // Show IDs for reference
    echo '<div style="margin-bottom: 12px; padding: 8px; background: #fff; border-radius: 3px; font-size: 12px;">';
    if ( ! empty( $mlsid ) ) {
        echo '<strong>MLS ID:</strong> <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">' . esc_html( $mlsid ) . '</code><br />';
    }
    echo '<strong>Vessel ID:</strong> <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">' . esc_html( $vessel_id ) . '</code>';
    echo '</div>';
    
    // Large, prominent link button
    echo '<p style="margin: 0;">';
    echo '<a href="' . esc_url( $yatco_listing_url ) . '" target="_blank" rel="noopener noreferrer" class="button button-primary" style="width: 100%; text-align: center; padding: 10px; font-size: 14px; font-weight: bold; text-decoration: none; display: block; box-sizing: border-box;">';
    echo '🌐 Open Original YATCO Listing';
    echo '</a>';
    echo '</p>';
    
    // Also show the URL as a clickable link below
    echo '<p style="margin: 8px 0 0 0; font-size: 11px; color: #666; word-break: break-all;">';
    echo 'Link: <a href="' . esc_url( $yatco_listing_url ) . '" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: underline;">' . esc_html( $yatco_listing_url ) . '</a>';
    echo '</p>';
    echo '</div>';
    
    $token = yatco_get_token();
    if ( empty( $token ) ) {
        echo '<p style="color: #d63638;"><strong>Error:</strong> YATCO API token is not configured. Please set it in <a href="' . esc_url( admin_url( 'options-general.php?page=yatco_api' ) ) . '">Settings → YATCO API</a>.</p>';
        return;
    }
    
    // Check if update was just performed
    if ( isset( $_GET['yatco_updated'] ) && $_GET['yatco_updated'] === '1' ) {
        echo '<div class="notice notice-success inline" style="margin: 10px 0;"><p><strong>✓ Vessel updated successfully!</strong></p></div>';
    }
    
    if ( isset( $_GET['yatco_update_error'] ) ) {
        $error_msg = sanitize_text_field( $_GET['yatco_update_error'] );
        echo '<div class="notice notice-error inline" style="margin: 10px 0;"><p><strong>Error:</strong> ' . esc_html( $error_msg ) . '</p></div>';
    }
    
    $last_updated = get_post_meta( $post->ID, 'yacht_last_updated', true );
    if ( $last_updated ) {
        $last_updated_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated );
        echo '<p style="font-size: 12px; color: #666; margin-bottom: 15px;"><strong>Last Updated:</strong> ' . esc_html( $last_updated_date ) . '</p>';
    }
    
    echo '<p>Click the button below to fetch the latest data for this vessel from the YATCO API and update all fields.</p>';
    
    $update_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=yatco_update_vessel&post_id=' . $post->ID ),
        'yatco_update_vessel_' . $post->ID
    );
    
    echo '<p><a href="' . esc_url( $update_url ) . '" class="button button-primary button-large" style="width: 100%; text-align: center;">🔄 Update Vessel from YATCO</a></p>';
    
    echo '<p style="font-size: 11px; color: #666; margin-top: 10px;">This will update all meta fields, images, and taxonomy terms with the latest data from YATCO.</p>';
}

/**
 * Add meta box for editing detailed specifications (Overview).
 */
function yatco_add_detailed_specs_meta_box() {
    add_meta_box(
        'yatco_detailed_specs',
        'Overview / Detailed Specifications',
        'yatco_detailed_specs_meta_box_callback',
        'yacht',
        'normal',
        'high'
    );
}

/**
 * Meta box callback - displays WYSIWYG editor for detailed specifications.
 */
function yatco_detailed_specs_meta_box_callback( $post ) {
    // Add nonce for security
    wp_nonce_field( 'yatco_save_detailed_specs', 'yatco_detailed_specs_nonce' );
    
    // Get current value
    $detailed_specs = get_post_meta( $post->ID, 'yacht_detailed_specifications', true );
    
    // If no content yet, show a helpful message
    if ( empty( $detailed_specs ) ) {
        echo '<p style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 15px;"><strong>No overview content yet.</strong> This content will be automatically imported when you click "Update Vessel from YATCO" in the sidebar. You can also manually add content here.</p>';
    }
    
    // Settings for wp_editor
    // The textarea_name must match the field name we check in save function
    $editor_settings = array(
        'textarea_name' => 'yacht_detailed_specifications', // This sets the form field name
        'textarea_rows' => 20,
        'media_buttons' => true,
        'teeny' => false,
        'tinymce' => array(
            'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_adv',
            'toolbar2' => 'formatselect,fontselect,fontsizeselect,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo',
            'toolbar3' => '',
            'toolbar4' => '',
        ),
        'quicktags' => true,
    );
    
    echo '<p style="margin-bottom: 15px; color: #666;">Edit the overview and detailed specifications content. This content appears in the collapsible "View Full Overview" section on the frontend.</p>';
    
    // Output the editor
    // The second parameter is the editor ID (used for DOM element ID)
    // The textarea_name in settings ensures the form field name is correct
    wp_editor( $detailed_specs, 'yacht_detailed_specifications_editor', $editor_settings );
    
    echo '<p style="margin-top: 15px; font-size: 12px; color: #666;"><strong>Note:</strong> This content will be displayed in a toggle section on the frontend. HTML tags are preserved.</p>';
    
    // Show content info
    if ( ! empty( $detailed_specs ) ) {
        echo '<p style="margin-top: 10px; font-size: 11px; color: #999;">Current content: ' . strlen( $detailed_specs ) . ' characters</p>';
    }
}

/**
 * Save detailed specifications when post is saved.
 */
function yatco_save_detailed_specs( $post_id ) {
    // Check if this is an autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // Check if this is a revision
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // Check post type
    if ( get_post_type( $post_id ) !== 'yacht' ) {
        return;
    }
    
    // Check user permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    // Verify nonce
    if ( ! isset( $_POST['yatco_detailed_specs_nonce'] ) || ! wp_verify_nonce( $_POST['yatco_detailed_specs_nonce'], 'yatco_save_detailed_specs' ) ) {
        return;
    }
    
    // Save the meta field
    // wp_editor submits content with the textarea_name, which is 'yacht_detailed_specifications'
    if ( isset( $_POST['yacht_detailed_specifications'] ) ) {
        // Sanitize content (allows HTML but removes dangerous tags)
        $detailed_specs = wp_kses_post( $_POST['yacht_detailed_specifications'] );
        update_post_meta( $post_id, 'yacht_detailed_specifications', $detailed_specs );
    } else {
        // If field is empty string, allow it to be cleared (empty content is valid)
        // This handles the case where user clears all content
        if ( isset( $_POST['yacht_detailed_specifications'] ) && $_POST['yacht_detailed_specifications'] === '' ) {
            update_post_meta( $post_id, 'yacht_detailed_specifications', '' );
        }
        // If field is not set at all, don't modify existing value
    }
}

/**
 * Handle Update Vessel request.
 */
function yatco_handle_update_vessel() {
    // Check user permissions
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'You do not have permission to update vessels.' );
    }
    
    // Get post ID
    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
    
    if ( ! $post_id ) {
        wp_redirect( admin_url( 'edit.php?post_type=yacht&yatco_update_error=' . urlencode( 'Invalid post ID' ) ) );
        exit;
    }
    
    // Verify nonce
    $nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
    if ( ! wp_verify_nonce( $nonce, 'yatco_update_vessel_' . $post_id ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( 'Security check failed' ) ) );
        exit;
    }
    
    // Check if post exists and is a yacht
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'yacht' ) {
        wp_redirect( admin_url( 'edit.php?post_type=yacht&yatco_update_error=' . urlencode( 'Post not found or not a yacht' ) ) );
        exit;
    }
    
    // Get vessel ID
    $vessel_id = get_post_meta( $post_id, 'yacht_vessel_id', true );
    
    if ( empty( $vessel_id ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( 'No YATCO vessel ID found for this post' ) ) );
        exit;
    }
    
    // Get API token
    $token = yatco_get_token();
    if ( empty( $token ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( 'YATCO API token not configured' ) ) );
        exit;
    }
    
    // Update the vessel
    require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
    $result = yatco_import_single_vessel( $token, $vessel_id );
    
    if ( is_wp_error( $result ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( $result->get_error_message() ) ) );
        exit;
    }
    
    // Success - redirect back to edit screen with success message
    wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_updated=1' ) );
    exit;
}