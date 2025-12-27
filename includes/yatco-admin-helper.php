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
    // Check if import was stopped - if so, don't show progress
    $stop_flag = get_option( 'yatco_import_stop_flag', false );
    if ( $stop_flag !== false ) {
        // Import was stopped - clear any stale progress
        delete_transient( 'yatco_import_progress' );
        delete_transient( 'yatco_daily_sync_progress' );
        wp_cache_delete( 'yatco_import_progress', 'transient' );
        wp_cache_delete( 'yatco_daily_sync_progress', 'transient' );
        $import_progress = false;
        $daily_sync_progress = false;
        $cache_status = 'Import stopped by user.';
    } else {
        // Get all progress data
        $import_progress = get_transient( 'yatco_import_progress' );
        $daily_sync_progress = get_transient( 'yatco_daily_sync_progress' );
        $cache_status = get_transient( 'yatco_cache_warming_status' );
    }
    
    // Determine if import is active
    $active_stage = 0;
    $active_progress = null;
    if ( $import_progress !== false && is_array( $import_progress ) ) {
        $active_stage = 'full';
        $active_progress = $import_progress;
    } elseif ( $daily_sync_progress !== false && is_array( $daily_sync_progress ) ) {
        $active_stage = 'daily_sync';
        $active_progress = $daily_sync_progress;
    }
    
    echo '<h2>Import Status & Progress</h2>';
    
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
            $processed = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : 0;
            $failed = isset( $active_progress['failed'] ) ? intval( $active_progress['failed'] ) : 0;
            $attempted = isset( $active_progress['attempted'] ) ? intval( $active_progress['attempted'] ) : 0;
            $pending = isset( $active_progress['pending'] ) ? intval( $active_progress['pending'] ) : 0;
            $total_to_process = isset( $active_progress['total'] ) ? intval( $active_progress['total'] ) : 0;
            $total_from_api = isset( $active_progress['total_from_api'] ) ? intval( $active_progress['total_from_api'] ) : $total_to_process;
            $already_imported = isset( $active_progress['already_imported'] ) ? intval( $active_progress['already_imported'] ) : 0;
            
            // Use attempted for progress bar (shows actual progress through the list)
            $current = $attempted > 0 ? $attempted : $processed;
            $total = $total_to_process;
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
        
        // Show detailed counts for full import
        if ( $active_stage === 'full' && isset( $total_from_api ) ) {
            echo '<div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px; font-size: 13px;">';
            echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 10px;">';
            echo '<div><strong>Total from API:</strong> <span id="yatco-total-from-api">' . number_format( $total_from_api ) . '</span></div>';
            echo '<div><strong>Already Imported:</strong> <span id="yatco-already-imported">' . number_format( $already_imported ) . '</span></div>';
            echo '<div style="color: #46b450;"><strong>✓ Successful:</strong> <span id="yatco-successful-count">' . number_format( $processed ) . '</span></div>';
            echo '<div style="color: #dc3232;"><strong>✗ Failed:</strong> <span id="yatco-failed-count">' . number_format( $failed ) . '</span></div>';
            echo '<div style="color: #2271b1;"><strong>⏳ Pending:</strong> <span id="yatco-pending-count">' . number_format( $pending ) . '</span></div>';
            echo '</div>';
            echo '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; color: #666;">';
            echo '<strong>Progress:</strong> <span id="yatco-processed-count">' . number_format( $current ) . ' / ' . number_format( $total ) . '</span> attempted';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px; color: #666;">';
            echo '<span><strong>Processed:</strong> <span id="yatco-processed-count">' . number_format( $current ) . ' / ' . number_format( $total ) . '</span></span>';
            echo '<span><strong>Remaining:</strong> <span id="yatco-remaining-count">' . number_format( $total - $current ) . '</span></span>';
            echo '</div>';
        }
        
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
        echo '<p style="margin: 0; color: #666;">No active import. Start a full import or daily sync below to see progress.</p>';
    }
    
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
        echo '        // Update counts - handle both full import (with detailed counts) and daily sync (simple counts)';
        echo '        if (progressData.processed !== undefined && $("#yatco-successful-count").length) {';
        echo '            // Full import with detailed counts';
        echo '            if (progressData.total_from_api !== undefined && $("#yatco-total-from-api").length) {';
        echo '                $("#yatco-total-from-api").text(progressData.total_from_api.toLocaleString());';
        echo '            }';
        echo '            if (progressData.already_imported !== undefined && $("#yatco-already-imported").length) {';
        echo '                $("#yatco-already-imported").text(progressData.already_imported.toLocaleString());';
        echo '            }';
        echo '            if ($("#yatco-successful-count").length) {';
        echo '                $("#yatco-successful-count").text(progressData.processed.toLocaleString());';
        echo '            }';
        echo '            if (progressData.failed !== undefined && $("#yatco-failed-count").length) {';
        echo '                $("#yatco-failed-count").text(progressData.failed.toLocaleString());';
        echo '            }';
        echo '            if (progressData.pending !== undefined && $("#yatco-pending-count").length) {';
        echo '                $("#yatco-pending-count").text(progressData.pending.toLocaleString());';
        echo '            }';
        echo '            if ($("#yatco-processed-count").length) {';
        echo '                $("#yatco-processed-count").text(progressData.attempted.toLocaleString() + " / " + progressData.total.toLocaleString());';
        echo '            }';
        echo '        } else {';
        echo '            // Daily sync or simple counts';
        echo '            if (progressData.current !== undefined && $("#yatco-processed-count").length && !$("#yatco-successful-count").length) {';
        echo '                $("#yatco-processed-count").text(progressData.current.toLocaleString() + " / " + progressData.total.toLocaleString());';
        echo '            }';
        echo '            if (progressData.remaining !== undefined && $("#yatco-remaining-count").length) {';
        echo '                $("#yatco-remaining-count").text(progressData.remaining.toLocaleString());';
        echo '            }';
        echo '        }';
        echo '        ';
    echo '        // Update ETA';
    echo '        if (progressData.eta_minutes !== undefined && $("#yatco-eta-text").length) {';
    echo '            $("#yatco-eta-text").text(progressData.eta_minutes + " minutes");';
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
            echo '            success: function(response) {';
            echo '                if (response && response.success && response.data) {';
            echo '                    if (response.data.active && response.data.progress) {';
            echo '                        updateProgressBar(response.data.progress);';
            echo '                    }';
            echo '                }';
            echo '            }';
    echo '        });';
    echo '    }';
    echo '    ';
    echo '    // Listen to heartbeat tick (primary method)';
    echo '    $(document).on("heartbeat-tick", function(event, data) {';
    echo '        console.log("[YATCO] Heartbeat tick received, data:", data);';
    echo '        if (data.yatco_import_progress) {';
    echo '            console.log("[YATCO] Heartbeat: Progress data found, updating display");';
    echo '            // Heartbeat sends the full progress object, extract progress field if needed';
    echo '            var progressData = data.yatco_import_progress.progress || data.yatco_import_progress;';
    echo '            if (data.yatco_import_progress.status) {';
    echo '                progressData.status = data.yatco_import_progress.status;';
    echo '            }';
    echo '            updateProgressBar(progressData);';
    echo '        } else {';
    echo '            console.log("[YATCO] Heartbeat: No progress data in response");';
    echo '        }';
    echo '    });';
    echo '    ';
    echo '    // Also poll via AJAX every 1 second as backup';
    echo '    fetchProgress(); // Initial fetch';
    echo '    pollInterval = setInterval(fetchProgress, 1000); // Poll every 1 second';
    echo '    ';
    echo '    console.log("YATCO Status Section: Progress updates enabled (Heartbeat + AJAX polling every 1s)");';
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

