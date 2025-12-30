<?php
/**
 * Admin Helper Functions
 * 
 * Helper functions for admin display
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display Import Status & Progress section (reusable)
 */
function yatco_display_import_status_section() {
    // Get all progress data from wp_options (more reliable than transients)
    require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
    $import_progress = yatco_get_import_status( 'full' );
    $daily_sync_progress = yatco_get_import_status( 'daily_sync' );
    $cache_status_raw = yatco_get_import_status_message();
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    
    // Determine if import or sync is active - check ACTIVE processes FIRST
    $active_stage = 0;
    $active_progress = null;
    
    // Check for active daily sync first (takes priority)
    if ( $daily_sync_progress !== false && is_array( $daily_sync_progress ) ) {
        $active_stage = 'daily_sync';
        $active_progress = $daily_sync_progress;
        // If sync is active, clear stop flag (sync was started after stop)
        if ( $stop_flag !== false ) {
            delete_option( 'yatco_import_stop_flag' );
            delete_transient( 'yatco_cache_warming_stop' );
            $stop_flag = false;
        }
    } elseif ( $import_progress !== false && is_array( $import_progress ) ) {
        // Check if import is actually active (not stopped)
        if ( $stop_flag === false ) {
            // No stop flag, import is active
            $active_stage = 'full';
            $active_progress = $import_progress;
        } else {
            // Stop flag is set but we have progress - check if status says stopped
            // If status doesn't say stopped, clear the flag (it's stale)
            if ( $cache_status_raw === false || stripos( $cache_status_raw, 'stopped' ) === false ) {
                delete_option( 'yatco_import_stop_flag' );
                delete_transient( 'yatco_cache_warming_stop' );
                $stop_flag = false;
                $active_stage = 'full';
                $active_progress = $import_progress;
            }
        }
    }
    
    // Determine cache status based on active processes
    $cache_status = false;
    if ( $active_stage !== 0 ) {
        // Active process running - use status from transient
        if ( $cache_status_raw !== false ) {
            $cache_status = $cache_status_raw;
        } elseif ( $active_stage === 'daily_sync' ) {
            $cache_status = 'Daily Sync: Starting...';
        } elseif ( $active_stage === 'full' ) {
            $cache_status = 'Full Import: Starting...';
        }
    } elseif ( $stop_flag !== false ) {
        // No active process and stop flag is set - show stopped status
        if ( $cache_status_raw === false || stripos( $cache_status_raw, 'stopped' ) !== false ) {
            $cache_status = 'Import stopped by user.';
        } else {
            // Stop flag is set but status doesn't say stopped - clear the flag (stale)
            delete_option( 'yatco_import_stop_flag' );
            delete_transient( 'yatco_cache_warming_stop' );
            $stop_flag = false;
            $cache_status = $cache_status_raw !== false ? $cache_status_raw : false;
        }
    } else {
        // No active process and no stop flag - use status from transient or default
        $cache_status = $cache_status_raw !== false ? $cache_status_raw : false;
    }
    
    echo '<h2>Import Status & Progress</h2>';
    
    // ALWAYS render the progress bar structure - let JavaScript populate it
    // This ensures the progress bar is always visible and can be updated in real-time
    echo '<div id="yatco-import-status-display" style="background: #fff; border: 2px solid #2271b1; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    
    // Determine initial values from current progress (if available)
    $initial_stage_name = 'Full Import';
    $initial_percent = 0;
    $initial_current = 0;
    $initial_total = 0;
    $initial_status = $cache_status !== false ? $cache_status : 'No active import';
    
    if ( $active_stage === 'daily_sync' && $active_progress ) {
        $initial_stage_name = 'Daily Sync';
        $initial_current = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : 0;
        $initial_total = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
        $initial_percent = $initial_total > 0 ? round( ( $initial_current / $initial_total ) * 100, 1 ) : 0;
    } elseif ( $active_stage === 'full' && $active_progress ) {
        $initial_stage_name = 'Full Import';
        $processed = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : 0;
        $attempted = isset( $active_progress['attempted'] ) ? intval( $active_progress['attempted'] ) : 0;
        $initial_current = $attempted > 0 ? $attempted : $processed;
        $initial_total = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
        $initial_percent = isset( $active_progress['percent'] ) ? floatval( $active_progress['percent'] ) : ( $initial_total > 0 ? round( ( $initial_current / $initial_total ) * 100, 1 ) : 0 );
    } elseif ( ( $import_progress !== false && is_array( $import_progress ) ) || ( $daily_sync_progress !== false && is_array( $daily_sync_progress ) ) ) {
        // There's progress data but stop flag might be set - still show it
        if ( $daily_sync_progress !== false && is_array( $daily_sync_progress ) ) {
            $initial_stage_name = 'Daily Sync';
            $initial_current = isset( $daily_sync_progress['processed'] ) ? intval( $daily_sync_progress['processed'] ) : 0;
            $initial_total = isset( $daily_sync_progress['total'] ) ? intval( $daily_sync_progress['total'] ) : 0;
            $initial_percent = $initial_total > 0 ? round( ( $initial_current / $initial_total ) * 100, 1 ) : 0;
        } elseif ( $import_progress !== false && is_array( $import_progress ) ) {
            $initial_stage_name = 'Full Import';
            $processed = isset( $import_progress['processed'] ) ? intval( $import_progress['processed'] ) : 0;
            $attempted = isset( $import_progress['attempted'] ) ? intval( $import_progress['attempted'] ) : 0;
            $initial_current = $attempted > 0 ? $attempted : $processed;
            $initial_total = isset( $import_progress['total'] ) ? intval( $import_progress['total'] ) : 0;
            $initial_percent = isset( $import_progress['percent'] ) ? floatval( $import_progress['percent'] ) : ( $initial_total > 0 ? round( ( $initial_current / $initial_total ) * 100, 1 ) : 0 );
        }
        if ( $cache_status_raw !== false ) {
            $initial_status = $cache_status_raw;
        }
    }
    
    // Always render the progress structure - JavaScript will update it
    echo '<h3 id="yatco-stage-name" style="margin-top: 0; color: #2271b1;">📊 ' . esc_html( $initial_stage_name ) . ' Progress</h3>';
    echo '<p id="yatco-status-text" style="margin: 10px 0; font-size: 14px; color: #666;"><strong>Status:</strong> <span>' . esc_html( $initial_status ) . '</span></p>';
    
    echo '<div style="background: #f0f0f0; border-radius: 10px; height: 30px; margin: 15px 0; position: relative; overflow: hidden;">';
    echo '<div id="yatco-progress-bar" style="background: linear-gradient(90deg, #2271b1 0%, #46b450 100%); height: 100%; width: ' . esc_attr( $initial_percent ) . '%; transition: width 0.5s ease-in-out; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 12px;">';
    echo esc_html( $initial_percent ) . '%';
    echo '</div>';
    echo '</div>';
    
    // Always render full import detailed counts structure (JavaScript will show/hide as needed)
    echo '<div id="yatco-full-import-details" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px; font-size: 13px; ' . ( $active_stage === 'full' && isset( $total_from_api ) ? '' : 'display: none;' ) . '">';
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 10px;">';
    echo '<div><strong>Total from API:</strong> <span id="yatco-total-from-api">' . ( isset( $total_from_api ) ? number_format( $total_from_api ) : '0' ) . '</span></div>';
    echo '<div><strong>Already Imported:</strong> <span id="yatco-already-imported">' . ( isset( $already_imported ) ? number_format( $already_imported ) : '0' ) . '</span></div>';
    echo '<div style="color: #46b450;"><strong>✓ Successful:</strong> <span id="yatco-successful-count">' . ( isset( $processed ) ? number_format( $processed ) : '0' ) . '</span></div>';
    echo '<div style="color: #dc3232;"><strong>✗ Failed:</strong> <span id="yatco-failed-count">' . ( isset( $failed ) ? number_format( $failed ) : '0' ) . '</span></div>';
    echo '<div style="color: #2271b1;"><strong>⏳ Pending:</strong> <span id="yatco-pending-count">' . ( isset( $pending ) ? number_format( $pending ) : '0' ) . '</span></div>';
    echo '</div>';
    echo '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; color: #666;">';
    echo '<strong>Progress:</strong> <span id="yatco-processed-count">' . number_format( $initial_current ) . ' / ' . number_format( $initial_total ) . '</span> attempted';
    echo '</div>';
    echo '</div>';
    
    // Always render simple progress counts (for daily sync or fallback)
    echo '<div id="yatco-simple-progress" style="display: ' . ( $active_stage === 'daily_sync' || ( $active_stage !== 'full' && ! isset( $total_from_api ) ) ? 'flex' : 'none' ) . '; justify-content: space-between; margin-top: 10px; font-size: 13px; color: #666;">';
    echo '<span><strong>Processed:</strong> <span id="yatco-processed-count-simple">' . number_format( $initial_current ) . ' / ' . number_format( $initial_total ) . '</span></span>';
    echo '<span><strong>Remaining:</strong> <span id="yatco-remaining-count">' . number_format( max( 0, $initial_total - $initial_current ) ) . '</span></span>';
    echo '</div>';
    
    // Always render daily sync details structure (JavaScript will show/hide as needed)
    $removed = isset( $active_progress['removed'] ) ? intval( $active_progress['removed'] ) : 0;
    $new = isset( $active_progress['new'] ) ? intval( $active_progress['new'] ) : 0;
    $price_updates = isset( $active_progress['price_updates'] ) ? intval( $active_progress['price_updates'] ) : 0;
    $days_updates = isset( $active_progress['days_on_market_updates'] ) ? intval( $active_progress['days_on_market_updates'] ) : 0;
    echo '<div id="yatco-daily-sync-details" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; font-size: 13px; color: #666; ' . ( $active_stage === 'daily_sync' ? '' : 'display: none;' ) . '">';
    echo '<p style="margin: 5px 0;"><strong>Removed:</strong> <span id="yatco-removed-count">' . number_format( $removed ) . '</span></p>';
    echo '<p style="margin: 5px 0;"><strong>New:</strong> <span id="yatco-new-count">' . number_format( $new ) . '</span></p>';
    echo '<p style="margin: 5px 0;"><strong>Price Updates:</strong> <span id="yatco-price-updates-count">' . number_format( $price_updates ) . '</span></p>';
    echo '<p style="margin: 5px 0;"><strong>Days on Market Updates:</strong> <span id="yatco-days-updates-count">' . number_format( $days_updates ) . '</span></p>';
    echo '</div>';
    
    // Always render ETA
    echo '<p id="yatco-eta-container" style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Estimated time remaining:</strong> <span id="yatco-eta-text">calculating...</span></p>';
    
    // Always render stop button
    echo '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0;">';
    echo '<form method="post" id="yatco-stop-import-form" style="display: inline-block;">';
    wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
    echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; padding: 8px 16px;">🛑 Stop Import</button>';
    echo '</form>';
    echo '<p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">Click to cancel the current import. The import will stop at the next checkpoint.</p>';
    echo '</div>';
    
    echo '</div>';
    
    // Real-time progress bar update script - using BOTH Heartbeat API AND AJAX polling
    echo '<script type="text/javascript">';
    echo 'jQuery(document).ready(function($) {';
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
    echo '            progressBar.css("width", percent + "%");';
    echo '            progressBar.text(percent.toFixed(1) + "%");';
    echo '        }';
    echo '        ';
    echo '        // Update status text';
    echo '        if (progressData.status && $("#yatco-status-text").length) {';
    echo '            $("#yatco-status-text").html("<strong>Status:</strong> <span>" + progressData.status + "</span>");';
    echo '        }';
    echo '        ';
    echo '        // Update counts - handle both full import (with detailed counts) and daily sync (simple counts)';
    echo '        if (progressData.total_from_api !== undefined) {';
    echo '            // Full import with detailed counts - show detailed section';
    echo '            $("#yatco-full-import-details").show();';
    echo '            $("#yatco-simple-progress").hide();';
    echo '            $("#yatco-daily-sync-details").hide();';
    echo '            ';
    echo '            if ($("#yatco-total-from-api").length) {';
    echo '                $("#yatco-total-from-api").text((progressData.total_from_api || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-already-imported").length) {';
    echo '                $("#yatco-already-imported").text((progressData.already_imported || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-successful-count").length) {';
    echo '                $("#yatco-successful-count").text((progressData.processed || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-failed-count").length) {';
    echo '                $("#yatco-failed-count").text((progressData.failed || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-pending-count").length) {';
    echo '                $("#yatco-pending-count").text((progressData.pending || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-processed-count").length) {';
    echo '                var attempted = progressData.attempted || progressData.current || 0;';
    echo '                var total = progressData.total || 0;';
    echo '                $("#yatco-processed-count").text(attempted.toLocaleString() + " / " + total.toLocaleString());';
    echo '            }';
    echo '        } else if (progressData.removed !== undefined || progressData.new !== undefined) {';
    echo '            // Daily sync - show daily sync details';
    echo '            $("#yatco-full-import-details").hide();';
    echo '            $("#yatco-simple-progress").show();';
    echo '            $("#yatco-daily-sync-details").show();';
    echo '            ';
    echo '            if ($("#yatco-removed-count").length) {';
    echo '                $("#yatco-removed-count").text((progressData.removed || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-new-count").length) {';
    echo '                $("#yatco-new-count").text((progressData.new || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-price-updates-count").length) {';
    echo '                $("#yatco-price-updates-count").text((progressData.price_updates || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-days-updates-count").length) {';
    echo '                $("#yatco-days-updates-count").text((progressData.days_on_market_updates || 0).toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-processed-count-simple").length) {';
    echo '                var current = progressData.current || 0;';
    echo '                var total = progressData.total || 0;';
    echo '                $("#yatco-processed-count-simple").text(current.toLocaleString() + " / " + total.toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-remaining-count").length) {';
    echo '                var remaining = (progressData.remaining !== undefined) ? progressData.remaining : (progressData.total || 0) - (progressData.current || 0);';
    echo '                $("#yatco-remaining-count").text(Math.max(0, remaining).toLocaleString());';
    echo '            }';
    echo '        } else {';
    echo '            // Simple progress (fallback)';
    echo '            $("#yatco-full-import-details").hide();';
    echo '            $("#yatco-simple-progress").show();';
    echo '            $("#yatco-daily-sync-details").hide();';
    echo '            ';
    echo '            if ($("#yatco-processed-count-simple").length) {';
    echo '                var current = progressData.current || progressData.attempted || 0;';
    echo '                var total = progressData.total || 0;';
    echo '                $("#yatco-processed-count-simple").text(current.toLocaleString() + " / " + total.toLocaleString());';
    echo '            }';
    echo '            if ($("#yatco-remaining-count").length) {';
    echo '                var remaining = (progressData.remaining !== undefined) ? progressData.remaining : (progressData.total || 0) - (progressData.current || progressData.attempted || 0);';
    echo '                $("#yatco-remaining-count").text(Math.max(0, remaining).toLocaleString());';
    echo '            }';
    echo '        }';
    echo '        ';
    echo '        // Update ETA';
    echo '        if (progressData.eta_minutes !== undefined && $("#yatco-eta-text").length) {';
    echo '            $("#yatco-eta-text").text(progressData.eta_minutes + " minutes");';
    echo '        } else if ($("#yatco-eta-text").length) {';
    echo '            // Check if import is stopped';
    echo '            var statusText = progressData.status || "";';
    echo '            if (statusText.toLowerCase().indexOf("stopped") !== -1 || !progressData.active) {';
    echo '                $("#yatco-eta-text").text("stopped");';
    echo '            } else {';
    echo '                $("#yatco-eta-text").text("calculating...");';
    echo '            }';
    echo '        }';
    echo '        ';
    echo '        // Update stage name if provided';
    echo '        if (progressData.stage && $("#yatco-stage-name").length) {';
    echo '            var stageName = progressData.stage === "daily_sync" ? "Daily Sync" : "Full Import";';
    echo '            $("#yatco-stage-name").text("📊 " + stageName + " Progress");';
    echo '        }';
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
    echo '            timeout: 5000,';
    echo '            success: function(response) {';
    echo '                if (response && response.success && response.data) {';
    echo '                    var data = response.data;';
    echo '                    if (data.active && data.progress) {';
    echo '                        // Add status and stage to progress data if available';
    echo '                        if (data.status) {';
    echo '                            data.progress.status = data.status;';
    echo '                        }';
    echo '                        if (data.stage) {';
    echo '                            data.progress.stage = data.stage;';
    echo '                        }';
    echo '                        updateProgressBar(data.progress);';
    echo '                    } else if (data.progress) {';
    echo '                        // Even if not marked as active, if we have progress data, show it';
    echo '                        if (data.status) {';
    echo '                            data.progress.status = data.status;';
    echo '                        }';
    echo '                        if (data.stage) {';
    echo '                            data.progress.stage = data.stage;';
    echo '                        }';
    echo '                        updateProgressBar(data.progress);';
    echo '                    }';
    echo '                }';
    echo '            },';
    echo '            error: function(xhr, status, error) {';
    echo '                // Silently retry on error';
    echo '            }';
    echo '        });';
    echo '    }';
    echo '    ';
    echo '    // Listen to heartbeat tick (backup method - AJAX polling is primary)';
    echo '    $(document).on("heartbeat-tick", function(event, data) {';
    echo '        if (data.yatco_import_progress) {';
    echo '            var progressInfo = data.yatco_import_progress;';
    echo '            if (progressInfo.active && progressInfo.progress) {';
    echo '                var progressData = progressInfo.progress;';
    echo '                if (progressInfo.status) {';
    echo '                    progressData.status = progressInfo.status;';
    echo '                }';
    echo '                if (progressInfo.stage) {';
    echo '                    progressData.stage = progressInfo.stage;';
    echo '                }';
    echo '                updateProgressBar(progressData);';
    echo '            } else if (progressInfo.progress) {';
    echo '                var progressData = progressInfo.progress;';
    echo '                if (progressInfo.status) {';
    echo '                    progressData.status = progressInfo.status;';
    echo '                }';
    echo '                if (progressInfo.stage) {';
    echo '                    progressData.stage = progressInfo.stage;';
    echo '                }';
    echo '                updateProgressBar(progressData);';
    echo '            }';
    echo '        }';
    echo '    });';
    echo '    ';
    echo '    // Poll via AJAX every 1 second (primary method for real-time updates)';
    echo '    fetchProgress(); // Initial fetch immediately';
    echo '    pollInterval = setInterval(fetchProgress, 1000); // Poll every 1 second';
    echo '});';
    echo '</script>';
}

/**
 * Display Import Log Viewer section with real-time updates
 */
function yatco_display_import_logs() {
    $logs = get_option( 'yatco_import_logs', array() );
    
    echo '<h2>Import Activity Log</h2>';
    echo '<div id="yatco-import-logs-container" style="background: #fff; border: 2px solid #2271b1; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    echo '<div id="yatco-import-logs" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; border-radius: 4px; padding: 15px; max-height: 400px; overflow-y: auto; font-family: "Courier New", monospace; font-size: 12px; line-height: 1.6;">';
    
    if ( ! empty( $logs ) ) {
        // Show last 50 log entries
        $recent_logs = array_slice( $logs, -50 );
        foreach ( $recent_logs as $log_entry ) {
            $timestamp = isset( $log_entry['timestamp'] ) ? $log_entry['timestamp'] : '';
            $level = isset( $log_entry['level'] ) ? strtoupper( $log_entry['level'] ) : 'INFO';
            $message = isset( $log_entry['message'] ) ? esc_html( $log_entry['message'] ) : '';
            
            // Color code by level
            $color = '#d4d4d4'; // default
            if ( $level === 'ERROR' || $level === 'CRITICAL' ) {
                $color = '#f48771';
            } elseif ( $level === 'WARNING' ) {
                $color = '#dcdcaa';
            } elseif ( $level === 'INFO' ) {
                $color = '#4ec9b0';
            } elseif ( $level === 'DEBUG' ) {
                $color = '#808080';
            }
            
            echo '<div class="yatco-log-entry" data-timestamp="' . esc_attr( $timestamp ) . '" style="margin-bottom: 5px; color: ' . esc_attr( $color ) . ';">';
            echo '<span style="color: #808080;">[' . esc_html( $timestamp ) . ']</span> ';
            echo '<strong style="color: ' . esc_attr( $color ) . ';">[' . esc_html( $level ) . ']</strong> ';
            echo esc_html( $message );
            echo '</div>';
        }
    } else {
        echo '<div style="color: #808080; font-style: italic;">No log entries yet. Logs will appear here when you start an import.</div>';
    }
    
    echo '</div>';
    echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">Showing last 50 log entries. Logs update automatically every 2 seconds. Logs are cleared when they exceed 100 entries.</p>';
    echo '</div>';
    
    // Real-time log update script - simplified to always update
    $log_nonce = wp_create_nonce( 'yatco_get_import_logs_nonce' );
    echo '<script type="text/javascript">';
    echo 'jQuery(document).ready(function($) {';
    echo '    var lastLogCount = 0;';
    echo '    var logUpdateInterval = null;';
    echo '    var updateCount = 0;';
    echo '    ';
    echo '    function updateLogs() {';
    echo '        updateCount++;';
    echo '        $.ajax({';
    echo '            url: ajaxurl,';
    echo '            type: "POST",';
    echo '            data: {';
    echo '                action: "yatco_get_import_logs",';
    echo '                _ajax_nonce: ' . json_encode( $log_nonce ) . '';
    echo '            },';
    echo '            cache: false,';
    echo '            timeout: 5000,';
    echo '            success: function(response) {';
    echo '                if (response && response.success && response.data && response.data.logs) {';
    echo '                    var logs = response.data.logs;';
    echo '                    var currentLogCount = logs.length;';
    echo '                    ';
    echo '                    // Always update if log count changed or first update';
    echo '                    if (currentLogCount !== lastLogCount || updateCount === 1) {';
    echo '                        var logHtml = "";';
    echo '                        var levelColors = {';
    echo '                            "ERROR": "#f48771",';
    echo '                            "CRITICAL": "#f48771",';
    echo '                            "WARNING": "#dcdcaa",';
    echo '                            "INFO": "#4ec9b0",';
    echo '                            "DEBUG": "#808080"';
    echo '                        };';
    echo '                        ';
    echo '                        if (logs.length === 0) {';
    echo '                            logHtml = "<div style=\\"color: #808080; font-style: italic;\\">No log entries yet. Logs will appear here when you start an import.</div>";';
    echo '                        } else {';
    echo '                            $.each(logs, function(i, log) {';
    echo '                                var level = (log.level || "info").toUpperCase();';
    echo '                                var color = levelColors[level] || "#d4d4d4";';
    echo '                                var timestamp = log.timestamp || "";';
    echo '                                var message = log.message || "";';
    echo '                                logHtml += "<div class=\\"yatco-log-entry\\" style=\\"margin-bottom: 5px; color: " + color + ";\\">";';
    echo '                                logHtml += "<span style=\\"color: #808080;\\">[" + timestamp + "]</span> ";';
    echo '                                logHtml += "<strong style=\\"color: " + color + ";\\">[" + level + "]</strong> ";';
    echo '                                // Escape HTML in message';
    echo '                                var msgDiv = $("<div>").text(message);';
    echo '                                logHtml += msgDiv.html();';
    echo '                                logHtml += "</div>";';
    echo '                            });';
    echo '                        }';
    echo '                        ';
    echo '                        var container = $("#yatco-import-logs");';
    echo '                        var wasNearBottom = false;';
    echo '                        if (container.length && container[0].scrollHeight > 0) {';
    echo '                            var scrollDiff = container[0].scrollHeight - container.scrollTop() - container.height();';
    echo '                            if (scrollDiff < 150) {';
    echo '                                wasNearBottom = true;';
    echo '                            }';
    echo '                        } else {';
    echo '                            wasNearBottom = true; // Auto-scroll on first load';
    echo '                        }';
    echo '                        ';
    echo '                        container.html(logHtml);';
    echo '                        lastLogCount = currentLogCount;';
    echo '                        ';
    echo '                        // Auto-scroll to bottom if was near bottom';
    echo '                        if (wasNearBottom && container.length) {';
    echo '                            setTimeout(function() {';
    echo '                                container.scrollTop(container[0].scrollHeight);';
    echo '                            }, 10);';
    echo '                        }';
    echo '                        ';
    echo '                        console.log("YATCO: Logs updated (#" + updateCount + ", " + logs.length + " entries, was: " + (lastLogCount - (logs.length - lastLogCount)) + ")")';
    echo '                    } else {';
    echo '                        console.log("YATCO: Logs unchanged (" + logs.length + " entries)");';
    echo '                    }';
    echo '                } else {';
    echo '                    console.log("YATCO: Log update failed - invalid response", response);';
    echo '                }';
    echo '            },';
    echo '            error: function(xhr, status, error) {';
    echo '                console.log("YATCO: Log update error (#" + updateCount + ")", status, error);';
    echo '            }';
    echo '        });';
    echo '    }';
    echo '    ';
    echo '    // Initialize lastLogCount from existing logs on page';
    echo '    lastLogCount = $(".yatco-log-entry").length;';
    echo '    ';
    echo '    // Update logs immediately, then every 2 seconds';
    echo '    updateLogs();';
    echo '    logUpdateInterval = setInterval(updateLogs, 2000);';
    echo '    ';
    echo '    console.log("YATCO: Log viewer initialized (polling every 2s, initial count: " + lastLogCount + ")");';
    echo '});';
    echo '</script>';
}

