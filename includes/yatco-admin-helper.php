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
    // Get all progress data
    $import_progress = get_transient( 'yatco_import_progress' );
    $daily_sync_progress = get_transient( 'yatco_daily_sync_progress' );
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    
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
            $current = isset( $active_progress['processed'] ) ? intval( $active_progress['processed'] ) : ( isset( $active_progress['last_processed'] ) ? intval( $active_progress['last_processed'] ) : 0 );
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
        echo '<p style="margin: 0; color: #666;">No active import. Start a full import or daily sync below to see progress.</p>';
    }
    
    echo '</div>';
}

/**
 * Display Import Log Viewer section
 */
function yatco_display_import_logs() {
    $logs = get_option( 'yatco_import_logs', array() );
    
    echo '<h2>Import Activity Log</h2>';
    echo '<div id="yatco-import-logs" style="background: #1e1e1e; color: #d4d4d4; border: 2px solid #3c3c3c; border-radius: 4px; padding: 15px; margin: 20px 0; max-height: 500px; overflow-y: auto; font-family: "Courier New", monospace; font-size: 12px; line-height: 1.6;">';
    
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
            
            echo '<div class="yatco-log-entry" style="margin-bottom: 5px; color: ' . esc_attr( $color ) . ';">';
            echo '<span style="color: #808080;">[' . esc_html( $timestamp ) . ']</span> ';
            echo '<strong style="color: ' . esc_attr( $color ) . ';">[' . esc_html( $level ) . ']</strong> ';
            echo esc_html( $message );
            echo '</div>';
        }
    } else {
        echo '<div style="color: #808080; font-style: italic;">No log entries yet. Logs will appear here when you start an import.</div>';
    }
    
    echo '</div>';
    echo '<p style="font-size: 12px; color: #666; margin-top: -10px;">Showing last 50 log entries. Logs are automatically cleared when they exceed 100 entries.</p>';
}

