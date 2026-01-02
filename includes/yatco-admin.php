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
        'Auto-Update Vessels',
        'yatco_auto_refresh_cache_render',
        'yatco_api',
        'yatco_api_section'
    );

    add_settings_field(
        'yatco_daily_sync_enabled',
        'Daily Sync',
        'yatco_daily_sync_enabled_render',
        'yatco_api',
        'yatco_api_section'
    );

    add_settings_field(
        'yatco_daily_sync_frequency',
        'Daily Sync Frequency',
        'yatco_daily_sync_frequency_render',
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
    echo '<label>Automatically update all vessels every 6 hours</label>';
    echo '<p class="description">Enable this to automatically sync and update all vessels from the YATCO API every 6 hours. Requires a server cron job to be configured (see Troubleshooting tab).</p>';
}

function yatco_daily_sync_enabled_render() {
    $options = get_option( 'yatco_api_settings' );
    $enabled = isset( $options['yatco_daily_sync_enabled'] ) ? $options['yatco_daily_sync_enabled'] : 'no';
    echo '<input type="checkbox" name="yatco_api_settings[yatco_daily_sync_enabled]" value="yes" ' . checked( $enabled, 'yes', false ) . ' />';
    echo '<label>Enable automatic Daily Sync</label>';
    echo '<p class="description">Enable this to automatically check for new, removed, or updated vessels based on the frequency setting below. Requires a server cron job to be configured.</p>';
}

function yatco_daily_sync_frequency_render() {
    $options = get_option( 'yatco_api_settings' );
    $frequency = isset( $options['yatco_daily_sync_frequency'] ) ? $options['yatco_daily_sync_frequency'] : 'daily';
    
    $frequencies = array(
        'hourly' => 'Every Hour',
        '6hours' => 'Every 6 Hours',
        '12hours' => 'Every 12 Hours',
        'daily' => 'Once Daily (Recommended)',
        '2days' => 'Every 2 Days',
        'weekly' => 'Once Weekly',
    );
    
    echo '<select name="yatco_api_settings[yatco_daily_sync_frequency]">';
    foreach ( $frequencies as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '" ' . selected( $frequency, $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">How often to run Daily Sync. Daily Sync checks for new vessels, removed vessels, and updates prices/days on market for existing vessels.</p>';
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
        // Handle stop import form submission FIRST (before displaying anything)
        if ( isset( $_POST['yatco_stop_import'] ) && check_admin_referer( 'yatco_stop_import', 'yatco_stop_import_nonce' ) ) {
            yatco_log( '🛑 IMPORT STOP REQUESTED: Stop button clicked by user (Import Tab)', 'warning' );
            
            require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
            
            // Get current progress before clearing (use wp_options, not transients)
            $current_progress = yatco_get_import_status( 'full' );
            $processed = 0;
            $total = 0;
            if ( $current_progress !== false && is_array( $current_progress ) ) {
                $processed = isset( $current_progress['processed'] ) ? intval( $current_progress['processed'] ) : ( isset( $current_progress['last_processed'] ) ? intval( $current_progress['last_processed'] ) : 0 );
                $total = isset( $current_progress['total'] ) ? intval( $current_progress['total'] ) : 0;
            }
            
            yatco_log( "🛑 IMPORT STOP: Current progress was {$processed}/{$total} vessels", 'warning' );
            
            // Set stop flag using WordPress option (more reliable for direct runs)
            update_option( 'yatco_import_stop_flag', time(), false );
            set_transient( 'yatco_cache_warming_stop', time(), 900 );
            
            // Disable auto-resume
            delete_option( 'yatco_import_auto_resume' );
            yatco_log( '🛑 IMPORT STOP: Auto-resume disabled', 'warning' );
            
            // Clear progress transients (for backward compatibility)
            delete_transient( 'yatco_import_progress' );
            delete_transient( 'yatco_daily_sync_progress' );
            wp_cache_delete( 'yatco_import_progress', 'transient' );
            wp_cache_delete( 'yatco_daily_sync_progress', 'transient' );
            yatco_log( '🛑 IMPORT STOP: Progress cleared from cache and database', 'warning' );
            
            // CRITICAL: Release the locks immediately so user can start a new import/sync
            // The import/sync process will detect the stop flag and stop, but we need to clear the locks
            // so a new import/sync can start immediately without waiting
            $had_import_lock = get_option( 'yatco_import_lock', false );
            $had_sync_lock = get_option( 'yatco_daily_sync_lock', false );
            delete_option( 'yatco_import_lock' );
            delete_option( 'yatco_import_process_id' );
            delete_option( 'yatco_import_using_fastcgi' );
            delete_option( 'yatco_daily_sync_lock' );
            delete_option( 'yatco_daily_sync_process_id' );
            if ( $had_import_lock !== false ) {
                yatco_log( '🛑 IMPORT STOP: Import lock released immediately to allow new import', 'warning' );
            }
            if ( $had_sync_lock !== false ) {
                yatco_log( '🛑 IMPORT STOP: Daily sync lock released immediately to allow new sync', 'warning' );
            }
            
            // Cancel ALL scheduled cron jobs to prevent them from running after stop
            $scheduled_full = wp_next_scheduled( 'yatco_full_import_hook' );
            $scheduled_warm = wp_next_scheduled( 'yatco_warm_cache_hook' );
            $scheduled_sync = wp_next_scheduled( 'yatco_daily_sync_hook' );
            wp_clear_scheduled_hook( 'yatco_full_import_hook' );
            wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
            wp_clear_scheduled_hook( 'yatco_daily_sync_hook' );
            if ( $scheduled_full !== false ) {
                yatco_log( '🛑 IMPORT STOP: Cancelled scheduled Full Import event', 'warning' );
            }
            if ( $scheduled_warm !== false ) {
                yatco_log( '🛑 IMPORT STOP: Cancelled scheduled update all vessels event', 'warning' );
            }
            if ( $scheduled_sync !== false ) {
                yatco_log( '🛑 IMPORT STOP: Cancelled scheduled Daily Sync event', 'warning' );
            }
            
            // Clear progress from wp_options (not just transients)
            require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
            yatco_clear_import_status( 'full' );
            yatco_clear_import_status( 'daily_sync' );
            yatco_clear_import_status_message();
            
            // Update status
            yatco_update_import_status_message( 'Import stopped by user.', 300 );
            yatco_log( '🛑 IMPORT STOP COMPLETE: Stop flag set and lock released. Ready for new import.', 'warning' );
            
            // Redirect to prevent form resubmission
            // Only redirect if headers haven't been sent yet
            if ( ! headers_sent() ) {
                wp_safe_redirect( admin_url( 'options-general.php?page=yatco_api&tab=import&stopped=1' ) );
                exit;
            } else {
                // Headers already sent, output JavaScript redirect instead
                echo '<script type="text/javascript">window.location.href="' . esc_js( admin_url( 'options-general.php?page=yatco_api&tab=import&stopped=1' ) ) . '";</script>';
                exit;
            }
        }
        
        echo '<div class="yatco-import-section">';
        
        // Show success message if stopped
        if ( isset( $_GET['stopped'] ) && $_GET['stopped'] == '1' ) {
            echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>Import stop requested.</strong> The import will stop at the next checkpoint.</p></div>';
        }
        
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
        echo '<h3 style="margin-top: 0;">Full Import</h3>';
        echo '<p style="color: #666; margin-bottom: 20px;">Import all vessels from YATCO. The import will run in the background - you\'ll be redirected to the Status page to monitor progress.</p>';
        echo '<form method="post" id="yatco-full-import-form" style="margin: 0;">';
        wp_nonce_field( 'yatco_full_import_direct', 'yatco_full_import_direct_nonce' );
        submit_button( 'Run Full Import', 'primary large', 'yatco_full_import_direct', false, array( 'style' => 'font-size: 14px; padding: 10px 20px; height: auto;' ) );
        echo '</form>';
        echo '</div>';

        // Direct run handler - trigger import via AJAX since wp-cron.php returns 404
        if ( isset( $_POST['yatco_full_import_direct'] ) && check_admin_referer( 'yatco_full_import_direct', 'yatco_full_import_direct_nonce' ) ) {
            if ( empty( $token ) ) {
                yatco_log( 'Full Import Direct: Import attempt failed - missing token', 'error' );
                echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
            } else {
                yatco_log( 'Full Import Direct: Import triggered via Direct button', 'info' );
                
                // Since wp-cron.php returns 404 via HTTP, use AJAX to trigger import immediately
                // The server cron job (every 5 min) will handle scheduled wp-cron events, but for
                // immediate execution on button click, we use AJAX which calls the hook directly
                yatco_log( 'Full Import Direct: Using AJAX method (wp-cron.php not accessible via HTTP)', 'info' );
                
                // Clear any existing stop flag before starting new import
                // This is CRITICAL - if stop flag is still set from previous stop, import will stop immediately
                $had_stop_flag = get_option( 'yatco_import_stop_flag', false );
                $had_stop_transient = get_transient( 'yatco_cache_warming_stop' );
                delete_option( 'yatco_import_stop_flag' );
                delete_transient( 'yatco_cache_warming_stop' );
                wp_cache_delete( 'yatco_import_stop_flag', 'options' );
                yatco_log( "Full Import Direct: Cleared stop flags before starting - Had stop flag: " . ( $had_stop_flag !== false ? 'YES (' . $had_stop_flag . ')' : 'NO' ) . ", Had transient: " . ( $had_stop_transient !== false ? 'YES' : 'NO' ), 'info' );
                
                // Clear old progress from both transients and wp_options (for consistency)
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
                yatco_clear_import_status( 'full' );
                yatco_clear_import_status( 'daily_sync' );
                yatco_clear_import_status_message();
                delete_transient( 'yatco_import_progress' );
                delete_transient( 'yatco_cache_warming_status' );
                wp_cache_delete( 'yatco_import_progress', 'transient' );
                wp_cache_delete( 'yatco_cache_warming_status', 'transient' );
                
                // Enable auto-resume for direct runs (will automatically continue if it times out)
                update_option( 'yatco_import_auto_resume', time(), false );
                
                // Clear any stale locks older than 5 minutes
                $import_lock = get_option( 'yatco_import_lock', false );
                if ( $import_lock !== false ) {
                    $lock_time = intval( $import_lock );
                    $lock_age = time() - $lock_time;
                    if ( $lock_age > 300 ) {
                        yatco_log( "Full Import Direct: Clearing stale lock (age: {$lock_age}s) before starting", 'info' );
                        delete_option( 'yatco_import_lock' );
                        delete_option( 'yatco_import_process_id' );
                        delete_option( 'yatco_import_using_fastcgi' );
                    } else {
                        yatco_log( "Full Import Direct: Import already running (lock age: {$lock_age}s), skipping", 'warning' );
                        wp_safe_redirect( admin_url( 'options-general.php?page=yatco_api&tab=status&import_already_running=1' ) );
                        exit;
                    }
                }
                
                // Save initial progress immediately so UI shows activity
                yatco_update_import_status( array(
                    'processed' => 0,
                    'total' => 0,
                    'last_processed' => 0,
                    'status' => 'starting',
                    'timestamp' => time(),
                    'updated' => time(),
                ), 'full' );
                yatco_update_import_status_message( 'Full Import: Scheduled - will start when wp-cron executes (next: :19 or :49 past hour)' );
                yatco_log( 'Full Import Direct: Initial progress saved for UI display', 'info' );
                
                // Since wp-cron.php returns 404 via HTTP, schedule the event and use spawn_cron() to trigger it
                // spawn_cron() uses a local request that should work even if HTTP access doesn't
                yatco_log( 'Full Import Direct: Scheduling import via wp-cron for immediate execution', 'info' );
                
                // Schedule the import to run immediately via wp-cron
                $scheduled = wp_schedule_single_event( time(), 'yatco_full_import_hook' );
                if ( is_wp_error( $scheduled ) ) {
                    yatco_log( 'Full Import Direct: ERROR - Failed to schedule wp-cron event: ' . $scheduled->get_error_message(), 'error' );
                    yatco_update_import_status_message( 'Full Import Error: Failed to schedule import. ' . $scheduled->get_error_message() );
                    wp_safe_redirect( admin_url( 'options-general.php?page=yatco_api&tab=status&import_error=schedule_failed' ) );
                    exit;
                }
                yatco_log( 'Full Import Direct: Import scheduled via wp-cron hook', 'info' );
                
                // CRITICAL: Immediately spawn wp-cron to run the scheduled event
                // This forces WordPress to execute cron jobs right away instead of waiting
                // spawn_cron() makes a non-blocking local HTTP request that works even if wp-cron.php is not publicly accessible
                if ( function_exists( 'spawn_cron' ) ) {
                    yatco_log( 'Full Import Direct: Triggering wp-cron immediately via spawn_cron()', 'info' );
                    $spawned = spawn_cron();
                    yatco_log( 'Full Import Direct: spawn_cron() executed (returned: ' . ( $spawned ? 'true' : 'false' ) . ')', 'info' );
                    if ( $spawned ) {
                        yatco_update_import_status_message( 'Full Import: Scheduled and cron spawned - import should start within seconds' );
                    } else {
                        yatco_log( 'Full Import Direct: spawn_cron() returned false - event scheduled but may wait for server cron', 'warning' );
                        yatco_update_import_status_message( 'Full Import: Scheduled - will execute when wp-cron runs (next: :19 or :49 past hour)' );
                    }
                } else {
                    yatco_log( 'Full Import Direct: spawn_cron() function not available - event scheduled but may wait for server cron', 'warning' );
                    yatco_update_import_status_message( 'Full Import: Scheduled - will execute when wp-cron runs (next: :19 or :49 past hour)' );
                }
                
                // Redirect immediately to status page
                wp_safe_redirect( admin_url( 'options-general.php?page=yatco_api&tab=status&import_started=1' ) );
                exit;
            }
        }
        echo '</div>';
        
        // Daily Sync Section
        echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
        echo '<h3 style="margin-top: 0;">Daily Sync</h3>';
        echo '<p style="color: #666; margin-bottom: 20px;">Check for new, removed, or updated vessels. Configure automatic scheduling in Settings tab.</p>';
        
        // Show current settings status
        $options = get_option( 'yatco_api_settings', array() );
        $sync_enabled = isset( $options['yatco_daily_sync_enabled'] ) ? $options['yatco_daily_sync_enabled'] : 'no';
        $sync_frequency = isset( $options['yatco_daily_sync_frequency'] ) ? $options['yatco_daily_sync_frequency'] : 'daily';
        
        $frequency_labels = array(
            'hourly' => 'Every Hour',
            '6hours' => 'Every 6 Hours',
            '12hours' => 'Every 12 Hours',
            'daily' => 'Once Daily',
            '2days' => 'Every 2 Days',
            'weekly' => 'Once Weekly',
        );
        
        echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 15px;">';
        echo '<p style="margin: 0;"><strong>Automatic Sync:</strong> ';
        if ( $sync_enabled === 'yes' ) {
            echo '<span style="color: #46b450;">✓ Enabled</span> - ' . esc_html( $frequency_labels[ $sync_frequency ] ?? $sync_frequency );
            $next_scheduled = wp_next_scheduled( 'yatco_daily_sync_hook' );
            $last_run = get_option( 'yatco_daily_sync_last_run', false );
            
            // If enabled but not scheduled, reschedule it (wp-cron might have missed it)
            if ( ! $next_scheduled || $next_scheduled < time() ) {
                // Reschedule if missing or if the scheduled time has passed
                // Function should be available since yatco-custom-integration.php is included by WordPress
                if ( function_exists( 'yatco_schedule_next_daily_sync' ) ) {
                    yatco_schedule_next_daily_sync();
                    $next_scheduled = wp_next_scheduled( 'yatco_daily_sync_hook' );
                    if ( $next_scheduled ) {
                        echo ' <span style="color: #d63638;">(Rescheduled - Next run: ' . date( 'Y-m-d H:i:s', $next_scheduled ) . ')</span>';
                    } else {
                        echo ' <span style="color: #d63638;">(⚠ Rescheduling failed - please save settings to reschedule)</span>';
                    }
                } else {
                    echo ' <span style="color: #d63638;">(⚠ Not scheduled - please save settings to reschedule)</span>';
                }
            } elseif ( $next_scheduled ) {
                echo ' (Next run: ' . date( 'Y-m-d H:i:s', $next_scheduled ) . ')';
            } else {
                // Fallback case - should not reach here normally
                echo ' <span style="color: #d63638;">(⚠ Not scheduled)</span>';
            }
            
            // Check if previous run was missed/failed
            if ( $last_run !== false ) {
                $time_since_last_run = time() - $last_run;
                // Calculate expected interval based on frequency
                $expected_interval = 86400; // Default to 24 hours
                switch ( $sync_frequency ) {
                    case 'hourly':
                        $expected_interval = 3600;
                        break;
                    case '6hours':
                        $expected_interval = 21600;
                        break;
                    case '12hours':
                        $expected_interval = 43200;
                        break;
                    case 'daily':
                        $expected_interval = 86400;
                        break;
                    case '2days':
                        $expected_interval = 172800;
                        break;
                    case 'weekly':
                        $expected_interval = 604800;
                        break;
                }
                
                // If last run was more than 1.5x the expected interval, it was likely missed
                if ( $time_since_last_run > ( $expected_interval * 1.5 ) ) {
                    $days_overdue = floor( ( $time_since_last_run - $expected_interval ) / 86400 );
                    echo ' <span style="color: #d63638; font-weight: bold;">⚠ Last run: ' . date( 'Y-m-d H:i:s', $last_run ) . ' (' . $days_overdue . ' day' . ( $days_overdue != 1 ? 's' : '' ) . ' overdue)</span>';
                } else {
                    echo ' <span style="color: #666; font-size: 0.9em;">(Last run: ' . date( 'Y-m-d H:i:s', $last_run ) . ')</span>';
                }
            } else {
                echo ' <span style="color: #666; font-size: 0.9em;">(No previous runs recorded)</span>';
            }
            
            // Warn if wp-cron is disabled
            if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
                echo ' <span style="color: #d63638; font-weight: bold;">⚠ WP-Cron is disabled - you must set up a server cron job</span>';
            }
        } else {
            echo '<span style="color: #dc3232;">✗ Disabled</span>';
        }
        echo '</p>';
        echo '</div>';
        
        echo '<form method="post" id="yatco-daily-sync-form" style="margin: 0;">';
        wp_nonce_field( 'yatco_daily_sync', 'yatco_daily_sync_nonce' );
        submit_button( 'Run Daily Sync Now', 'secondary', 'yatco_daily_sync', false, array( 'style' => 'font-size: 14px; padding: 10px 20px; height: auto;' ) );
        echo '</form>';
        
        if ( isset( $_POST['yatco_daily_sync'] ) && check_admin_referer( 'yatco_daily_sync', 'yatco_daily_sync_nonce' ) ) {
            if ( empty( $token ) ) {
                echo '<div class="notice notice-error" style="margin-top: 15px;"><p>Missing token. Please configure your API token first.</p></div>';
            } else {
                yatco_log( 'Daily Sync Direct: Sync triggered via button', 'info' );
                
                // Check if daily sync is already running
                $sync_lock = get_option( 'yatco_daily_sync_lock', false );
                if ( $sync_lock !== false ) {
                    $lock_age = time() - intval( $sync_lock );
                    // If lock is older than 10 minutes, it's stale - clear it
                    if ( $lock_age > 600 ) {
                        yatco_log( "Daily Sync Direct: Clearing stale lock (age: {$lock_age}s) before starting", 'info' );
                        delete_option( 'yatco_daily_sync_lock' );
                        delete_option( 'yatco_daily_sync_process_id' );
                    } else {
                        yatco_log( "Daily Sync Direct: Daily sync already running (lock age: {$lock_age}s), skipping", 'warning' );
                        echo '<div class="notice notice-warning" style="margin-top: 15px;"><p>Daily sync is already running. Please wait for it to complete before starting a new one.</p></div>';
                        return; // Don't schedule another sync
                    }
                }
                
                // Clear any stale stop flags and status messages
                delete_option( 'yatco_import_stop_flag' );
                delete_transient( 'yatco_cache_warming_stop' );
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
                yatco_clear_import_status_message(); // Clear stale "stopped" messages
                
                // Set initial status for daily sync
                yatco_update_import_status_message( 'Daily Sync: Starting...' );
                yatco_log( 'Daily Sync Direct: Cleared stop flags and set initial status', 'info' );
                
                // Trigger daily sync immediately via AJAX (same approach as full import for reliability)
                // This ensures immediate execution without waiting for wp-cron
                $ajax_url = admin_url( 'admin-ajax.php' );
                $nonce = wp_create_nonce( 'yatco_daily_sync_ajax' );
                
                // Make a non-blocking AJAX request to trigger the sync
                $response = wp_remote_post( $ajax_url, array(
                    'timeout' => 1,
                    'blocking' => false,
                    'sslverify' => false,
                    'body' => array(
                        'action' => 'yatco_run_daily_sync_ajax',
                        'nonce' => $nonce,
                    ),
                    'cookies' => $_COOKIE, // Include cookies for authentication
                ) );
                
                yatco_log( 'Daily Sync Direct: Sync triggered via AJAX, redirecting to status page', 'info' );
                
                // Redirect immediately to status page to prevent timeout
                wp_safe_redirect( admin_url( 'options-general.php?page=yatco_api&tab=status&sync_started=1' ) );
                exit;
            }
        }
        
        // Display Daily Sync History
        $history = get_option( 'yatco_daily_sync_history', array() );
        if ( ! empty( $history ) && is_array( $history ) ) {
            echo '<hr style="margin: 30px 0;" />';
            echo '<h4>Daily Sync History</h4>';
            echo '<p style="color: #666; font-size: 13px; margin-bottom: 15px;">Last 30 days of Daily Sync results:</p>';
            
            // Sort by date (newest first)
            krsort( $history );
            $history = array_slice( $history, 0, 30, true ); // Show last 30 days
            
            echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">';
            echo '<table class="widefat" style="margin: 0;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px;">Date</th>';
            echo '<th style="text-align: left; padding: 8px;">Time</th>';
            echo '<th style="text-align: center; padding: 8px;">Removed</th>';
            echo '<th style="text-align: center; padding: 8px;">New</th>';
            echo '<th style="text-align: center; padding: 8px;">Price Updates</th>';
            echo '<th style="text-align: center; padding: 8px;">Days on Market Updates</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ( $history as $date => $result ) {
                $timestamp = isset( $result['timestamp'] ) ? intval( $result['timestamp'] ) : 0;
                $time_str = $timestamp > 0 ? date( 'H:i:s', $timestamp ) : 'N/A';
                $removed = isset( $result['removed'] ) ? intval( $result['removed'] ) : 0;
                $new = isset( $result['new'] ) ? intval( $result['new'] ) : 0;
                $price_updates = isset( $result['price_updates'] ) ? intval( $result['price_updates'] ) : 0;
                $days_updates = isset( $result['days_on_market_updates'] ) ? intval( $result['days_on_market_updates'] ) : 0;
                
                echo '<tr>';
                echo '<td style="padding: 8px;">' . esc_html( $date ) . '</td>';
                echo '<td style="padding: 8px; color: #666;">' . esc_html( $time_str ) . '</td>';
                echo '<td style="text-align: center; padding: 8px;">' . ( $removed > 0 ? '<span style="color: #dc3232;">' . number_format( $removed ) . '</span>' : '0' ) . '</td>';
                echo '<td style="text-align: center; padding: 8px;">' . ( $new > 0 ? '<span style="color: #46b450;">+' . number_format( $new ) . '</span>' : '0' ) . '</td>';
                echo '<td style="text-align: center; padding: 8px;">' . ( $price_updates > 0 ? '<span style="color: #2271b1;">' . number_format( $price_updates ) . '</span>' : '0' ) . '</td>';
                echo '<td style="text-align: center; padding: 8px;">' . ( $days_updates > 0 ? '<span style="color: #2271b1;">' . number_format( $days_updates ) . '</span>' : '0' ) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<hr style="margin: 30px 0;" />';
            echo '<h4>Daily Sync History</h4>';
            echo '<p style="color: #666; font-size: 13px;">No sync history yet. Run Daily Sync to start tracking results.</p>';
        }
        
        echo '</div>';
        
        echo '<hr style="margin: 30px 0;" />';
        
        // Real-time progress bar update - using BOTH Heartbeat API AND AJAX polling for maximum reliability
        echo '<script type="text/javascript">';
        echo 'jQuery(document).ready(function($) {';
        echo '    var lastUpdateTime = 0;';
        echo '    var pollInterval = null;';
        echo '    console.log("[YATCO] Progress monitoring initialized");';
        echo '    ';
        echo '    function updateProgressBar(progressData) {';
        echo '        console.log("[YATCO] updateProgressBar called with:", progressData);';
        echo '        if (!progressData) {';
        echo '            console.log("[YATCO] updateProgressBar: No progress data provided");';
        echo '            return;';
        echo '        }';
        echo '        ';
        echo '        var percent = progressData.percent || 0;';
        echo '        var progressBar = $("#yatco-progress-bar");';
        echo '        console.log("[YATCO] Progress bar element found:", progressBar.length > 0, "Percent:", percent);';
        echo '        if (progressBar.length) {';
        echo '            var currentWidth = parseFloat(progressBar.css("width").replace("%", "")) || 0;';
        echo '            if (Math.abs(currentWidth - percent) > 0.01) {';
        echo '                console.log("[YATCO] Updating progress bar from", currentWidth, "to", percent);';
        echo '                progressBar.css("width", percent + "%");';
        echo '                progressBar.text(percent.toFixed(1) + "%");';
        echo '            } else {';
        echo '                console.log("[YATCO] Progress bar unchanged (difference < 0.01%)");';
        echo '            }';
        echo '        } else {';
        echo '            console.warn("[YATCO] Progress bar element not found!");';
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
        echo '        lastUpdateTime = Date.now();';
        echo '    }';
        echo '    ';
        echo '    function fetchProgress() {';
        echo '        console.log("[YATCO] fetchProgress: Polling for import status...");';
        echo '        $.ajax({';
        echo '            url: ajaxurl,';
        echo '            type: "POST",';
        echo '            data: {';
        echo '                action: "yatco_get_import_status",';
        echo '                _ajax_nonce: ' . json_encode( wp_create_nonce( 'yatco_get_import_status_nonce' ) );
        echo '            },';
        echo '            cache: false,';
        echo '            success: function(response) {';
        echo '                console.log("[YATCO] fetchProgress: AJAX response received:", response);';
        echo '                if (response && response.success && response.data) {';
        echo '                    console.log("[YATCO] fetchProgress: Response data:", response.data);';
        echo '                    console.log("[YATCO] fetchProgress: Active status:", response.data.active);';
        echo '                    if (response.data.active && response.data.progress) {';
        echo '                        console.log("[YATCO] fetchProgress: Import is active, updating progress bar");';
        echo '                        updateProgressBar(response.data.progress);';
        echo '                    } else {';
        echo '                        console.log("[YATCO] fetchProgress: Import is NOT active (stopped/completed), hiding progress");';
        echo '                        // Import stopped or completed, hide progress';
        echo '                        $("#yatco-import-status-display").html("<p style=\\"margin: 0; color: #999; text-align: center; padding: 40px 0;\\">No active import</p>");';
        echo '                        if (pollInterval) {';
        echo '                            console.log("[YATCO] fetchProgress: Clearing poll interval");';
        echo '                            clearInterval(pollInterval);';
        echo '                        }';
        echo '                    }';
        echo '                } else {';
        echo '                    console.warn("[YATCO] fetchProgress: Invalid response structure:", response);';
        echo '                }';
        echo '            },';
        echo '            error: function(xhr, status, error) {';
        echo '                console.error("[YATCO] fetchProgress: AJAX error - Status:", status, "Error:", error, "Response:", xhr.responseText);';
        echo '            }';
        echo '        });';
        echo '    }';
        echo '    ';
        echo '    // Listen to heartbeat tick (primary method)';
        echo '    $(document).on("heartbeat-tick", function(event, data) {';
        echo '        console.log("[YATCO] Heartbeat tick received, data:", data);';
        echo '        if (data.yatco_import_progress) {';
        echo '            console.log("[YATCO] Heartbeat: yatco_import_progress found:", data.yatco_import_progress);';
        echo '            console.log("[YATCO] Heartbeat: Active status:", data.yatco_import_progress.active);';
        echo '            if (data.yatco_import_progress.active) {';
        echo '                console.log("[YATCO] Heartbeat: Import is active, updating progress bar");';
        echo '                updateProgressBar(data.yatco_import_progress);';
        echo '            } else {';
        echo '                console.log("[YATCO] Heartbeat: Import is NOT active (stopped/completed), hiding progress");';
        echo '                // Import stopped or completed';
        echo '                $("#yatco-import-status-display").html("<p style=\\"margin: 0; color: #999; text-align: center; padding: 40px 0;\\">No active import</p>");';
        echo '                if (pollInterval) {';
        echo '                    console.log("[YATCO] Heartbeat: Clearing poll interval");';
        echo '                    clearInterval(pollInterval);';
        echo '                }';
        echo '            }';
        echo '        } else {';
        echo '            console.log("[YATCO] Heartbeat: No yatco_import_progress in heartbeat data");';
        echo '        }';
        echo '        if (data.yatco_auto_resume) {';
        echo '            console.log("[YATCO] Auto-resuming import...");';
        echo '        }';
        echo '    });';
        echo '    ';
        echo '    // Handle stop button click';
        echo '    $("#yatco-stop-import-form").on("submit", function(e) {';
        echo '        console.log("[YATCO] 🛑 STOP BUTTON CLICKED - Form submission started");';
        echo '        var form = $(this);';
        echo '        var button = form.find("button[type=\\"submit\\"]");';
        echo '        button.prop("disabled", true).text("Stopping...");';
        echo '        console.log("[YATCO] 🛑 Stop button disabled, waiting for form submission...");';
        echo '        // Form will submit normally (POST request)';
        echo '    });';
        echo '    ';
        echo '    // Also poll via AJAX every 1 second as backup (ensures real-time updates)';
        echo '    fetchProgress(); // Initial fetch';
        echo '    pollInterval = setInterval(fetchProgress, 1000); // Poll every 1 second';
        echo '    console.log("[YATCO] Progress updates enabled (Heartbeat + AJAX polling every 1s)");';
        echo '});';
        echo '</script>';
        
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
        // Handle stop import form submission FIRST (before displaying anything)
        if ( isset( $_POST['yatco_stop_import'] ) && check_admin_referer( 'yatco_stop_import', 'yatco_stop_import_nonce' ) ) {
            yatco_log( '🛑 IMPORT STOP REQUESTED: Stop button clicked by user (Status Tab)', 'warning' );
            
            require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
            
            // Get current progress before clearing (use wp_options, not transients)
            $current_progress = yatco_get_import_status( 'full' );
            $processed = 0;
            $total = 0;
            if ( $current_progress !== false && is_array( $current_progress ) ) {
                $processed = isset( $current_progress['processed'] ) ? intval( $current_progress['processed'] ) : ( isset( $current_progress['last_processed'] ) ? intval( $current_progress['last_processed'] ) : 0 );
                $total = isset( $current_progress['total'] ) ? intval( $current_progress['total'] ) : 0;
            }
            
            yatco_log( "🛑 IMPORT STOP: Current progress was {$processed}/{$total} vessels", 'warning' );
            
            // Set stop flag using WordPress option (more reliable for direct runs)
            update_option( 'yatco_import_stop_flag', time(), false );
            set_transient( 'yatco_cache_warming_stop', time(), 900 );
            
            // Disable auto-resume
            delete_option( 'yatco_import_auto_resume' );
            yatco_log( '🛑 IMPORT STOP: Auto-resume disabled', 'warning' );
            
            // Clear progress transients (for backward compatibility)
            delete_transient( 'yatco_import_progress' );
            delete_transient( 'yatco_daily_sync_progress' );
            wp_cache_delete( 'yatco_import_progress', 'transient' );
            wp_cache_delete( 'yatco_daily_sync_progress', 'transient' );
            yatco_log( '🛑 IMPORT STOP: Progress cleared from cache and database', 'warning' );
            
            // CRITICAL: Release the lock immediately so user can start a new import
            // The import process will detect the stop flag and stop, but we need to clear the lock
            // so a new import can start immediately without waiting
            $had_import_lock = get_option( 'yatco_import_lock', false );
            $had_sync_lock = get_option( 'yatco_daily_sync_lock', false );
            delete_option( 'yatco_import_lock' );
            delete_option( 'yatco_import_process_id' );
            delete_option( 'yatco_import_using_fastcgi' );
            delete_option( 'yatco_daily_sync_lock' );
            delete_option( 'yatco_daily_sync_process_id' );
            if ( $had_import_lock !== false ) {
                yatco_log( '🛑 IMPORT STOP: Import lock released immediately to allow new import', 'warning' );
            }
            if ( $had_sync_lock !== false ) {
                yatco_log( '🛑 IMPORT STOP: Daily sync lock released immediately to allow new sync', 'warning' );
            }
            
            // Cancel ALL scheduled cron jobs to prevent them from running after stop
            $scheduled_full = wp_next_scheduled( 'yatco_full_import_hook' );
            $scheduled_warm = wp_next_scheduled( 'yatco_warm_cache_hook' );
            $scheduled_sync = wp_next_scheduled( 'yatco_daily_sync_hook' );
            wp_clear_scheduled_hook( 'yatco_full_import_hook' );
            wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
            wp_clear_scheduled_hook( 'yatco_daily_sync_hook' );
            if ( $scheduled_full !== false ) {
                yatco_log( '🛑 IMPORT STOP: Cancelled scheduled Full Import event', 'warning' );
            }
            if ( $scheduled_warm !== false ) {
                yatco_log( '🛑 IMPORT STOP: Cancelled scheduled update all vessels event', 'warning' );
            }
            if ( $scheduled_sync !== false ) {
                yatco_log( '🛑 IMPORT STOP: Cancelled scheduled Daily Sync event', 'warning' );
            }
            
            // Clear progress from wp_options (not just transients)
            require_once YATCO_PLUGIN_DIR . 'includes/yatco-progress.php';
            yatco_clear_import_status( 'full' );
            yatco_clear_import_status( 'daily_sync' );
            yatco_clear_import_status_message();
            
            // Update status
            yatco_update_import_status_message( 'Import stopped by user.', 300 );
            yatco_log( '🛑 IMPORT STOP COMPLETE: Stop flag set and lock released. Ready for new import.', 'warning' );
            
            // Redirect to prevent form resubmission
            // Only redirect if headers haven't been sent yet
            if ( ! headers_sent() ) {
                wp_safe_redirect( admin_url( 'options-general.php?page=yatco_api&tab=status&stopped=1' ) );
                exit;
            } else {
                // Headers already sent, output JavaScript redirect instead
                echo '<script type="text/javascript">window.location.href="' . esc_js( admin_url( 'options-general.php?page=yatco_api&tab=status&stopped=1' ) ) . '";</script>';
                exit;
            }
        }
        
        echo '<div class="yatco-status-section">';
        
        // Show success message if stopped or started
        if ( isset( $_GET['stopped'] ) && $_GET['stopped'] == '1' ) {
            echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>Import stop requested.</strong> The import will stop at the next checkpoint.</p></div>';
        }
        if ( isset( $_GET['sync_started'] ) && $_GET['sync_started'] == '1' ) {
            echo '<div class="notice notice-info is-dismissible" style="margin: 20px 0;"><p><strong>Daily Sync started.</strong> Progress will appear below.</p></div>';
        }
        
        // Use the helper function to display status (same as Import tab)
        yatco_display_import_status_section();
        
        // Status tab - real-time updates using WordPress Heartbeat API
        echo '<script type="text/javascript">';
        echo 'jQuery(document).ready(function($) {';
        echo '    console.log("[YATCO STATUS] Status monitoring initialized");';
        echo '    ';
        echo '    function updateStatusProgress(progressData) {';
        echo '        console.log("[YATCO STATUS] updateStatusProgress called with:", progressData);';
        echo '        if (!progressData || !progressData.active) {';
        echo '            console.log("[YATCO STATUS] Import is NOT active (stopped/completed), hiding progress");';
        echo '            // Import stopped or completed, hide progress';
        echo '            $("#yatco-import-status-display").html("<p style=\\"margin: 0; color: #999; text-align: center; padding: 40px 0;\\">No active import</p>");';
        echo '            return;';
        echo '        }';
        echo '        ';
        echo '        var percent = progressData.percent || 0;';
        echo '        var progressBar = $("#yatco-progress-bar");';
        echo '        console.log("[YATCO STATUS] Progress bar element found:", progressBar.length > 0, "Percent:", percent);';
        echo '        if (progressBar.length) {';
        echo '            console.log("[YATCO STATUS] Updating progress bar to", percent + "%");';
        echo '            progressBar.css("width", percent + "%");';
        echo '            progressBar.text(percent.toFixed(1) + "%");';
        echo '        } else {';
        echo '            console.warn("[YATCO STATUS] Progress bar element not found!");';
        echo '        }';
        echo '        ';
        echo '        // Update counts';
        echo '        if (progressData.current !== undefined && $("#yatco-processed-count").length) {';
        echo '            $("#yatco-processed-count").text(progressData.current.toLocaleString() + " / " + progressData.total.toLocaleString());';
        echo '        }';
        echo '        ';
        echo '        // Update ETA';
        echo '        if (progressData.eta_minutes !== undefined && $("#yatco-eta-text").length) {';
        echo '            $("#yatco-eta-text").text(progressData.eta_minutes + " min remaining");';
        echo '        } else if ($("#yatco-eta-text").length) {';
        echo '            // Check if import is stopped';
        echo '            var statusText = progressData.status || "";';
        echo '            if (statusText.toLowerCase().indexOf("stopped") !== -1 || !progressData.active) {';
        echo '                $("#yatco-eta-text").text("stopped");';
        echo '            } else {';
        echo '                $("#yatco-eta-text").text("calculating...");';
        echo '            }';
        echo '        }';
        echo '    }';
        echo '    ';
        echo '    // Listen to heartbeat tick for progress updates';
        echo '    $(document).on("heartbeat-tick", function(event, data) {';
        echo '        console.log("[YATCO STATUS] Heartbeat tick received, data:", data);';
        echo '        if (data.yatco_import_progress) {';
        echo '            console.log("[YATCO STATUS] Heartbeat: yatco_import_progress found:", data.yatco_import_progress);';
        echo '            console.log("[YATCO STATUS] Heartbeat: Active status:", data.yatco_import_progress.active);';
        echo '            updateStatusProgress(data.yatco_import_progress);';
        echo '        } else {';
        echo '            console.log("[YATCO STATUS] Heartbeat: No yatco_import_progress in heartbeat data");';
        echo '        }';
        echo '    });';
        echo '    ';
        echo '    // Handle stop button click';
        echo '    $("#yatco-stop-import-form").on("submit", function(e) {';
        echo '        console.log("[YATCO STATUS] 🛑 STOP BUTTON CLICKED - Form submission started");';
        echo '        var form = $(this);';
        echo '        var button = form.find("button[type=\\"submit\\"]");';
        echo '        button.prop("disabled", true).text("Stopping...");';
        echo '        console.log("[YATCO STATUS] 🛑 Stop button disabled, waiting for form submission...");';
        echo '        // Form will submit normally (POST request)';
        echo '    });';
        echo '    ';
        echo '    // Also poll via AJAX to check for stop';
        echo '    var statusPollInterval = setInterval(function() {';
        echo '        console.log("[YATCO STATUS] Polling for import status...");';
        echo '        $.ajax({';
        echo '            url: ajaxurl,';
        echo '            type: "POST",';
        echo '            data: {';
        echo '                action: "yatco_get_import_status",';
        echo '                _ajax_nonce: ' . json_encode( wp_create_nonce( 'yatco_get_import_status_nonce' ) );
        echo '            },';
        echo '            success: function(response) {';
        echo '                console.log("[YATCO STATUS] Poll response received:", response);';
        echo '                if (response && response.success && response.data) {';
        echo '                    console.log("[YATCO STATUS] Poll: Response data:", response.data);';
        echo '                    console.log("[YATCO STATUS] Poll: Active status:", response.data.active);';
        echo '                    if (!response.data.active) {';
        echo '                        console.log("[YATCO STATUS] Poll: Import is NOT active, hiding progress and clearing interval");';
        echo '                        $("#yatco-import-status-display").html("<p style=\\"margin: 0; color: #999; text-align: center; padding: 40px 0;\\">No active import</p>");';
        echo '                        clearInterval(statusPollInterval);';
        echo '                    } else if (response.data.progress) {';
        echo '                        console.log("[YATCO STATUS] Poll: Import is active, updating progress");';
        echo '                        updateStatusProgress(response.data.progress);';
        echo '                    }';
        echo '                } else {';
        echo '                    console.warn("[YATCO STATUS] Poll: Invalid response structure:", response);';
        echo '                }';
        echo '            },';
        echo '            error: function(xhr, status, error) {';
        echo '                console.error("[YATCO STATUS] Poll: AJAX error - Status:", status, "Error:", error, "Response:", xhr.responseText);';
        echo '            }';
        echo '        });';
        echo '    }, 2000);';
        echo '    console.log("[YATCO STATUS] Status polling enabled (every 2s)");';
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

    // View All Vessel IDs Section
    echo '<hr style="margin: 30px 0;" />';
    echo '<h2>View All Vessel IDs</h2>';
    echo '<div style="background: #e7f5ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">';
    echo '<p style="margin: 0; font-weight: bold; color: #004085;"><strong>📊 Get Total Vessel Count:</strong> Fetch all active vessel IDs from the YATCO API to see the complete list and get an accurate count.</p>';
    echo '</div>';
    echo '<p>This will fetch all active vessel IDs from the <code>/ForSale/vessel/activevesselmlsid</code> endpoint. This is the same endpoint used by the import process.</p>';
    echo '<form method="post" id="yatco-view-all-vessels-form">';
    wp_nonce_field( 'yatco_view_all_vessels', 'yatco_view_all_vessels_nonce' );
    submit_button( '🔍 Fetch All Vessel IDs', 'primary', 'yatco_view_all_vessels', false, array( 'id' => 'yatco-view-all-vessels-btn' ) );
    echo '</form>';
    
    if ( isset( $_POST['yatco_view_all_vessels'] ) && check_admin_referer( 'yatco_view_all_vessels', 'yatco_view_all_vessels_nonce' ) ) {
        $token = yatco_get_token();
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            echo '<h3 style="margin-top: 30px;">Fetching All Vessel IDs...</h3>';
            echo '<p style="color: #666; font-size: 13px;">This may take a few moments...</p>';
            
            // Fetch all vessel IDs (0 = no limit)
            $all_vessel_ids = yatco_get_active_vessel_ids( $token, 0 );
            
            if ( is_wp_error( $all_vessel_ids ) ) {
                echo '<div class="notice notice-error"><p>Error fetching vessel IDs: ' . esc_html( $all_vessel_ids->get_error_message() ) . '</p></div>';
            } elseif ( empty( $all_vessel_ids ) || ! is_array( $all_vessel_ids ) ) {
                echo '<div class="notice notice-warning"><p>No vessel IDs returned. The API response may be empty or invalid.</p></div>';
            } else {
                $total_count = count( $all_vessel_ids );
                echo '<div class="notice notice-success" style="background: #d4edda; border-left: 4px solid #46b450; padding: 15px; margin: 20px 0;">';
                echo '<p style="font-size: 18px; font-weight: bold; margin: 0 0 10px 0;"><strong>✅ Success!</strong></p>';
                echo '<p style="font-size: 16px; margin: 5px 0;"><strong>Total Active Vessels:</strong> <span style="color: #2271b1; font-size: 20px; font-weight: bold;">' . number_format( $total_count ) . '</span></p>';
                echo '</div>';
                
                // Check if user wants to fetch prices
                $fetch_prices = isset( $_POST['yatco_fetch_prices'] ) && $_POST['yatco_fetch_prices'] === '1';
                
                // Initialize array for vessels with prices
                $vessels_with_prices = array();
                if ( $fetch_prices ) {
                    echo '<div class="notice notice-info" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0;">';
                    echo '<p style="margin: 0;"><strong>⏳ Fetching prices for all vessels...</strong> This may take a few minutes for ' . number_format( $total_count ) . ' vessels.</p>';
                    echo '</div>';
                    
                    // Flush output so user sees the message
                    if ( ob_get_level() > 0 ) {
                        ob_flush();
                    }
                    flush();
                    
                    require_once YATCO_PLUGIN_DIR . 'includes/yatco-api.php';
                    $processed = 0;
                    $batch_size = 10; // Process in smaller batches to avoid timeout
                    
                    foreach ( $all_vessel_ids as $vessel_id ) {
                        $processed++;
                        
                        // Fetch basic details for price
                        $basic_details = yatco_fetch_basic_details( $token, $vessel_id );
                        $price_formatted = '';
                        
                        if ( ! is_wp_error( $basic_details ) && ! empty( $basic_details ) ) {
                            $result = isset( $basic_details['Result'] ) ? $basic_details['Result'] : array();
                            $basic  = isset( $basic_details['BasicInfo'] ) ? $basic_details['BasicInfo'] : array();
                            
                            // Check for "Price on Application" first
                            if ( ( isset( $result['PriceOnApplication'] ) && $result['PriceOnApplication'] ) || 
                                 ( isset( $basic['PriceOnApplication'] ) && $basic['PriceOnApplication'] ) ) {
                                $price_formatted = 'Price on Application';
                            } else {
                                // Extract price - check Result section first (most reliable)
                                // Priority: Result.AskingPriceFormatted > Result.AskingPriceCompare > BasicInfo.AskingPriceUSD > BasicInfo.AskingPrice
                                if ( isset( $result['AskingPriceFormatted'] ) && ! empty( $result['AskingPriceFormatted'] ) ) {
                                    // Already formatted (e.g., "$129,900 USD")
                                    $price_formatted = $result['AskingPriceFormatted'];
                                } elseif ( isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
                                    // Use AskingPriceCompare from Result (USD value)
                                    $currency = isset( $result['AskingPriceCurrencyText'] ) ? $result['AskingPriceCurrencyText'] : ( isset( $basic['Currency'] ) ? $basic['Currency'] : 'USD' );
                                    $price_formatted = $currency . ' ' . number_format( floatval( $result['AskingPriceCompare'] ), 0 );
                                } elseif ( isset( $result['AskingPrice'] ) && $result['AskingPrice'] > 0 ) {
                                    // Use AskingPrice from Result
                                    $currency = isset( $result['AskingPriceCurrencyText'] ) ? $result['AskingPriceCurrencyText'] : ( isset( $basic['Currency'] ) ? $basic['Currency'] : 'USD' );
                                    $price_formatted = $currency . ' ' . number_format( floatval( $result['AskingPrice'] ), 0 );
                                } elseif ( isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ) {
                                    // Fallback to BasicInfo.AskingPriceUSD
                                    $price_formatted = '$' . number_format( floatval( $basic['AskingPriceUSD'] ), 0 );
                                } elseif ( isset( $basic['AskingPriceFormatted'] ) && ! empty( $basic['AskingPriceFormatted'] ) ) {
                                    // Use BasicInfo.AskingPriceFormatted
                                    $price_formatted = $basic['AskingPriceFormatted'];
                                } elseif ( isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 ) {
                                    // Fallback to BasicInfo.AskingPrice
                                    $currency = isset( $basic['Currency'] ) ? $basic['Currency'] : 'USD';
                                    $price_formatted = $currency . ' ' . number_format( floatval( $basic['AskingPrice'] ), 0 );
                                } else {
                                    $price_formatted = 'N/A';
                                }
                            }
                        } else {
                            $price_formatted = 'N/A';
                        }
                        
                        $vessels_with_prices[] = array(
                            'id' => $vessel_id,
                            'price' => $price_formatted,
                        );
                        
                        // Flush output every batch to show progress
                        if ( $processed % $batch_size === 0 ) {
                            if ( ob_get_level() > 0 ) {
                                ob_flush();
                            }
                            flush();
                        }
                    }
                    
                    echo '<div class="notice notice-success" style="background: #d4edda; border-left: 4px solid #46b450; padding: 15px; margin: 20px 0;">';
                    echo '<p style="margin: 0;"><strong>✅ Finished fetching prices for ' . number_format( $processed ) . ' vessels!</strong></p>';
                    echo '</div>';
                } else {
                    // Build simple array structure for display without prices
                    foreach ( $all_vessel_ids as $vessel_id ) {
                        $vessels_with_prices[] = array(
                            'id' => $vessel_id,
                            'price' => null,
                        );
                    }
                }
                
                // Display the full JSON response with prices (if fetched)
                $vessels_json = wp_json_encode( $vessels_with_prices, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                
                echo '<h3 style="margin-top: 30px;">Full API Response (All Vessel IDs' . ( $fetch_prices ? ' with Prices' : '' ) . ')</h3>';
                echo '<p style="color: #666; font-size: 13px; margin-bottom: 15px;">View and search the complete list of all active vessel IDs' . ( $fetch_prices ? ' with their prices' : '. Click "Fetch Prices" to also load price information' ) . ':</p>';
                
                if ( ! $fetch_prices ) {
                    echo '<form method="post" style="margin-bottom: 15px;">';
                    wp_nonce_field( 'yatco_view_all_vessels', 'yatco_view_all_vessels_nonce' );
                    echo '<input type="hidden" name="yatco_view_all_vessels" value="1" />';
                    echo '<input type="hidden" name="yatco_fetch_prices" value="1" />';
                    submit_button( '💰 Fetch Prices for All Vessels', 'primary', 'yatco_fetch_prices_btn', false, array( 'style' => 'font-size: 13px; padding: 8px 16px; height: auto;' ) );
                    echo '<p class="description" style="margin-top: 5px; color: #666; font-size: 12px;">This will fetch price information for all ' . number_format( $total_count ) . ' vessels. This may take several minutes.</p>';
                    echo '</form>';
                }
                
                echo '<div style="margin-bottom: 15px;">';
                echo '<button type="button" id="yatco-toggle-all-vessels-api" class="button button-secondary" style="margin-right: 10px;">📋 View Full API Response</button>';
                echo '<input type="text" id="yatco-all-vessels-api-search" placeholder="Search vessel IDs or prices (Ctrl+F also works)..." class="regular-text" style="width: 350px; display: none;" />';
                echo '</div>';
                
                echo '<div id="yatco-all-vessels-api-display" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; border-radius: 4px; padding: 20px; max-height: 700px; overflow: auto; font-family: "Courier New", Courier, monospace; font-size: 13px; line-height: 1.6; display: none; position: relative;">';
                echo '<pre id="yatco-all-vessels-api-content" style="margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #d4d4d4;">';
                echo esc_html( $vessels_json );
                echo '</pre>';
                echo '</div>';
                
                // JavaScript for toggle and search functionality
                echo '<script type="text/javascript">';
                echo 'jQuery(document).ready(function($) {';
                echo '    var $toggleBtn = $("#yatco-toggle-all-vessels-api");';
                echo '    var $searchBox = $("#yatco-all-vessels-api-search");';
                echo '    var $apiDisplay = $("#yatco-all-vessels-api-display");';
                echo '    var $apiContent = $("#yatco-all-vessels-api-content");';
                echo '    var originalContent = ' . wp_json_encode( $vessels_json ) . ';';
                echo '    var isExpanded = false;';
                
                echo '    $toggleBtn.on("click", function() {';
                echo '        if (isExpanded) {';
                echo '            $apiDisplay.slideUp(300);';
                echo '            $searchBox.slideUp(200);';
                echo '            $toggleBtn.text("📋 View Full API Response");';
                echo '            isExpanded = false;';
                echo '            $searchBox.val("");';
                echo '            $apiContent.text(originalContent);';
                echo '        } else {';
                echo '            $apiDisplay.slideDown(300);';
                echo '            $searchBox.slideDown(200);';
                echo '            $toggleBtn.text("🔽 Hide Full API Response");';
                echo '            isExpanded = true;';
                echo '            $apiContent.html(originalContent);';
                echo '            $searchBox.focus();';
                echo '        }';
                echo '    });';
                
                echo '    var searchTimeout;';
                echo '    $searchBox.on("input keyup", function(e) {';
                echo '        if (e.ctrlKey && e.key === "f") { return; }';
                echo '        var searchTerm = $(this).val();';
                echo '        clearTimeout(searchTimeout);';
                echo '        if (searchTerm === "") { $apiContent.text(originalContent); return; }';
                echo '        searchTimeout = setTimeout(function() {';
                echo '            var regex = new RegExp("(" + searchTerm.replace(/[.*+?^${}()|[\\]\\\\]/g, "\\\\$&") + ")", "gi");';
                echo '            var highlightedContent = originalContent.replace(regex, "<mark style=\'background: #ffeb3b; color: #000; padding: 2px 4px; border-radius: 2px;\'>$1</mark>");';
                echo '            $apiContent.html(highlightedContent);';
                echo '            var firstMark = $apiContent.find("mark").first();';
                echo '            if (firstMark.length) {';
                echo '                $apiDisplay.animate({ scrollTop: firstMark.offset().top - $apiDisplay.offset().top + $apiDisplay.scrollTop() - 100 }, 300);';
                echo '            }';
                echo '        }, 300);';
                echo '    });';
                echo '});';
                echo '</script>';
            }
        }
    }

        echo '<hr style="margin: 30px 0;" />';
    echo '<h2>Test Single Vessel & Create Post</h2>';
    echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">';
    echo '<p style="margin: 0; font-weight: bold; color: #856404;"><strong>📝 Test & Import:</strong> Fetch a specific vessel from the YATCO API by ID, display its data structure, <strong>and create a CPT post</strong> so you can preview how the template looks.</p>';
    echo '</div>';
    echo '<p>This is useful for testing the single yacht template before importing all vessels. Enter a vessel ID or leave empty to use the first available vessel.</p>';
    echo '<form method="post" id="yatco-test-vessel-form">';
    wp_nonce_field( 'yatco_test_vessel', 'yatco_test_vessel_nonce' );
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="yatco_test_vessel_id">Vessel ID (Optional)</label></th>';
    echo '<td><input type="number" id="yatco_test_vessel_id" name="yatco_test_vessel_id" value="' . ( isset( $_POST['yatco_test_vessel_id'] ) ? esc_attr( intval( $_POST['yatco_test_vessel_id'] ) ) : '' ) . '" class="regular-text" placeholder="Leave empty to use first available vessel" /></td>';
    echo '</tr>';
    echo '</table>';
    submit_button( '🔍 Fetch Vessel & Create Test Post', 'secondary', 'yatco_test_vessel_data_only', false, array( 'id' => 'yatco-test-vessel-btn' ) );
    echo '</form>';
    
    // Add debug vessel ID section
    echo '<hr style="margin: 30px 0;">';
    echo '<h2>Debug Vessel ID</h2>';
    echo '<div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; margin: 15px 0;">';
    echo '<p style="margin: 0; font-weight: bold; color: #1565c0;"><strong>🔧 Debug Tool:</strong> Test a specific vessel ID to see which API endpoints work and get detailed response information.</p>';
    echo '</div>';
    echo '<p>Use this to debug why a specific vessel ID returns null. This will test all available API endpoints and show you exactly what responses you\'re getting.</p>';
    echo '<form method="post" id="yatco-debug-vessel-form">';
    wp_nonce_field( 'yatco_debug_vessel', 'yatco_debug_vessel_nonce' );
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="yatco_debug_vessel_id">Vessel ID</label></th>';
    echo '<td><input type="number" id="yatco_debug_vessel_id" name="yatco_debug_vessel_id" value="' . ( isset( $_POST['yatco_debug_vessel_id'] ) ? esc_attr( intval( $_POST['yatco_debug_vessel_id'] ) ) : '456057' ) . '" class="regular-text" required /></td>';
    echo '</tr>';
    echo '</table>';
    submit_button( '🔍 Debug Vessel ID', 'secondary', 'yatco_debug_vessel', false );
    echo '</form>';
    
    if ( isset( $_POST['yatco_debug_vessel'] ) && ! empty( $_POST['yatco_debug_vessel'] ) ) {
        if ( ! isset( $_POST['yatco_debug_vessel_nonce'] ) || ! wp_verify_nonce( $_POST['yatco_debug_vessel_nonce'], 'yatco_debug_vessel' ) ) {
            echo '<div class="notice notice-error"><p>Security check failed. Please refresh the page and try again.</p></div>';
        } elseif ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            $debug_vessel_id = isset( $_POST['yatco_debug_vessel_id'] ) ? intval( $_POST['yatco_debug_vessel_id'] ) : 0;
            if ( $debug_vessel_id > 0 ) {
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-api.php';
                echo yatco_debug_vessel_id( $token, $debug_vessel_id );
            } else {
                echo '<div class="notice notice-error"><p>Please enter a valid vessel ID.</p></div>';
            }
        }
    }
    
    // Add ID Conversion Testing section
    echo '<hr style="margin: 30px 0;">';
    echo '<h2>Test Vessel ID & MLS ID Conversion</h2>';
    echo '<div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px; margin: 15px 0;">';
    echo '<p style="margin: 0; font-weight: bold; color: #2e7d32;"><strong>🔄 ID Conversion Tool:</strong> Test the conversion between Vessel ID and MLS ID to verify the conversion endpoints are working correctly.</p>';
    echo '</div>';
    echo '<p>Enter either a Vessel ID or MLS ID to test bidirectional conversion. This helps verify that the conversion endpoints are working and that IDs can be converted in both directions.</p>';
    echo '<form method="post" id="yatco-test-id-conversion-form">';
    wp_nonce_field( 'yatco_test_id_conversion', 'yatco_test_id_conversion_nonce' );
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="yatco_test_conversion_id">Vessel ID or MLS ID</label></th>';
    echo '<td><input type="number" id="yatco_test_conversion_id" name="yatco_test_conversion_id" value="' . ( isset( $_POST['yatco_test_conversion_id'] ) ? esc_attr( intval( $_POST['yatco_test_conversion_id'] ) ) : '' ) . '" class="regular-text" placeholder="Enter Vessel ID or MLS ID" required /></td>';
    echo '</tr>';
    echo '</table>';
    submit_button( '🔄 Test ID Conversion', 'secondary', 'yatco_test_id_conversion', false );
    echo '</form>';
    
    if ( isset( $_POST['yatco_test_id_conversion'] ) && ! empty( $_POST['yatco_test_id_conversion'] ) ) {
        if ( ! isset( $_POST['yatco_test_id_conversion_nonce'] ) || ! wp_verify_nonce( $_POST['yatco_test_id_conversion_nonce'], 'yatco_test_id_conversion' ) ) {
            echo '<div class="notice notice-error"><p>Security check failed. Please refresh the page and try again.</p></div>';
        } elseif ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            $test_id = isset( $_POST['yatco_test_conversion_id'] ) ? intval( $_POST['yatco_test_conversion_id'] ) : 0;
            if ( $test_id > 0 ) {
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-api.php';
                
                echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
                echo '<h3>Testing ID Conversion for: ' . esc_html( $test_id ) . '</h3>';
                
                // Test 1: Try converting as MLS ID to Vessel ID
                echo '<h4>Test 1: Convert MLS ID → Vessel ID</h4>';
                $mls_to_vessel_endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/VesselID/' . intval( $test_id );
                echo '<p style="color: #666; font-size: 13px;"><strong>Endpoint:</strong> <code>' . esc_html( $mls_to_vessel_endpoint ) . '</code></p>';
                
                // Make direct API call to get raw response
                $mls_to_vessel_response = wp_remote_get(
                    $mls_to_vessel_endpoint,
                    array(
                        'headers' => array(
                            'Authorization' => 'Basic ' . $token,
                            'Accept'        => 'application/json',
                            'Content-Type'  => 'application/json',
                        ),
                        'timeout' => 15,
                    )
                );
                
                $mls_to_vessel_body = '';
                $mls_to_vessel_code = 0;
                if ( ! is_wp_error( $mls_to_vessel_response ) ) {
                    $mls_to_vessel_code = wp_remote_retrieve_response_code( $mls_to_vessel_response );
                    $mls_to_vessel_body = wp_remote_retrieve_body( $mls_to_vessel_response );
                }
                
                // Use the conversion function to get parsed result
                $vessel_id_result = yatco_convert_mlsid_to_vessel_id( $token, $test_id );
                
                if ( is_wp_error( $vessel_id_result ) ) {
                    echo '<p style="color: #dc3232; font-weight: bold;">❌ Failed: ' . esc_html( $vessel_id_result->get_error_message() ) . '</p>';
                    $converted_vessel_id = null;
                } else {
                    echo '<p style="color: #46b450; font-weight: bold;">✅ Success! MLS ID <strong>' . esc_html( $test_id ) . '</strong> → Vessel ID <strong>' . esc_html( $vessel_id_result ) . '</strong></p>';
                    $converted_vessel_id = $vessel_id_result;
                }
                
                // Show raw response (formatted as JSON if possible)
                echo '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;">';
                echo '<p style="margin: 0 0 5px 0; font-weight: bold;">Raw API Response (HTTP ' . esc_html( $mls_to_vessel_code ) . '):</p>';
                $mls_to_vessel_display = $mls_to_vessel_body ?: ( is_wp_error( $mls_to_vessel_response ) ? $mls_to_vessel_response->get_error_message() : 'No response body' );
                // Try to prettify JSON if it's valid JSON
                $mls_to_vessel_json = json_decode( $mls_to_vessel_display, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $mls_to_vessel_json ) ) {
                    $mls_to_vessel_display = json_encode( $mls_to_vessel_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                }
                echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; max-height: 300px; overflow-y: auto; font-size: 11px; margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . esc_html( $mls_to_vessel_display ) . '</pre>';
                echo '</div>';
                
                // Test 2: Try converting as Vessel ID to MLS ID
                echo '<h4>Test 2: Convert Vessel ID → MLS ID</h4>';
                $vessel_to_mls_endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/MLSID/' . intval( $test_id );
                echo '<p style="color: #666; font-size: 13px;"><strong>Endpoint:</strong> <code>' . esc_html( $vessel_to_mls_endpoint ) . '</code></p>';
                
                // Make direct API call to get raw response
                $vessel_to_mls_response = wp_remote_get(
                    $vessel_to_mls_endpoint,
                    array(
                        'headers' => array(
                            'Authorization' => 'Basic ' . $token,
                            'Accept'        => 'application/json',
                            'Content-Type'  => 'application/json',
                        ),
                        'timeout' => 15,
                    )
                );
                
                $vessel_to_mls_body = '';
                $vessel_to_mls_code = 0;
                if ( ! is_wp_error( $vessel_to_mls_response ) ) {
                    $vessel_to_mls_code = wp_remote_retrieve_response_code( $vessel_to_mls_response );
                    $vessel_to_mls_body = wp_remote_retrieve_body( $vessel_to_mls_response );
                }
                
                // Use the conversion function to get parsed result
                $mls_id_result = yatco_convert_vessel_id_to_mlsid( $token, $test_id );
                
                if ( is_wp_error( $mls_id_result ) ) {
                    echo '<p style="color: #dc3232; font-weight: bold;">❌ Failed: ' . esc_html( $mls_id_result->get_error_message() ) . '</p>';
                    $converted_mls_id = null;
                } else {
                    echo '<p style="color: #46b450; font-weight: bold;">✅ Success! Vessel ID <strong>' . esc_html( $test_id ) . '</strong> → MLS ID <strong>' . esc_html( $mls_id_result ) . '</strong></p>';
                    $converted_mls_id = $mls_id_result;
                }
                
                // Show raw response (formatted as JSON if possible)
                echo '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;">';
                echo '<p style="margin: 0 0 5px 0; font-weight: bold;">Raw API Response (HTTP ' . esc_html( $vessel_to_mls_code ) . '):</p>';
                $vessel_to_mls_display = $vessel_to_mls_body ?: ( is_wp_error( $vessel_to_mls_response ) ? $vessel_to_mls_response->get_error_message() : 'No response body' );
                // Try to prettify JSON if it's valid JSON
                $vessel_to_mls_json = json_decode( $vessel_to_mls_display, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $vessel_to_mls_json ) ) {
                    $vessel_to_mls_display = json_encode( $vessel_to_mls_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                }
                echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; max-height: 300px; overflow-y: auto; font-size: 11px; margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . esc_html( $vessel_to_mls_display ) . '</pre>';
                echo '</div>';
                
                // Test 3: Verify bidirectional conversion (round-trip test)
                echo '<h4>Test 3: Bidirectional Verification (Round-Trip Test)</h4>';
                if ( $converted_vessel_id && $converted_mls_id ) {
                    // The ID could be either type, so test both directions
                    if ( $converted_vessel_id == $test_id ) {
                        // Test ID is a Vessel ID - verify MLS ID → Vessel ID works
                        $round_trip_vessel = yatco_convert_mlsid_to_vessel_id( $token, $converted_mls_id );
                        if ( ! is_wp_error( $round_trip_vessel ) && $round_trip_vessel == $test_id ) {
                            echo '<p style="color: #46b450; font-weight: bold;">✅ Round-trip successful! MLS ID ' . esc_html( $converted_mls_id ) . ' → Vessel ID ' . esc_html( $round_trip_vessel ) . ' (matches original)</p>';
                        } else {
                            echo '<p style="color: #ff9800; font-weight: bold;">⚠️ Round-trip warning: MLS ID ' . esc_html( $converted_mls_id ) . ' → Vessel ID ' . esc_html( is_wp_error( $round_trip_vessel ) ? 'ERROR' : $round_trip_vessel ) . ' (does not match original ' . esc_html( $test_id ) . ')</p>';
                        }
                    } elseif ( $converted_mls_id == $test_id ) {
                        // Test ID is an MLS ID - verify Vessel ID → MLS ID works
                        $round_trip_mls = yatco_convert_vessel_id_to_mlsid( $token, $converted_vessel_id );
                        if ( ! is_wp_error( $round_trip_mls ) && $round_trip_mls == $test_id ) {
                            echo '<p style="color: #46b450; font-weight: bold;">✅ Round-trip successful! Vessel ID ' . esc_html( $converted_vessel_id ) . ' → MLS ID ' . esc_html( $round_trip_mls ) . ' (matches original)</p>';
                        } else {
                            echo '<p style="color: #ff9800; font-weight: bold;">⚠️ Round-trip warning: Vessel ID ' . esc_html( $converted_vessel_id ) . ' → MLS ID ' . esc_html( is_wp_error( $round_trip_mls ) ? 'ERROR' : $round_trip_mls ) . ' (does not match original ' . esc_html( $test_id ) . ')</p>';
                        }
                    } else {
                        echo '<p style="color: #666;">ℹ️ Original ID (' . esc_html( $test_id ) . ') appears to be neither the Vessel ID (' . esc_html( $converted_vessel_id ) . ') nor the MLS ID (' . esc_html( $converted_mls_id ) . '). Testing round-trip...</p>';
                        // Try both directions
                        $rt_vessel = yatco_convert_mlsid_to_vessel_id( $token, $converted_mls_id );
                        $rt_mls = yatco_convert_vessel_id_to_mlsid( $token, $converted_vessel_id );
                        if ( ! is_wp_error( $rt_vessel ) && ! is_wp_error( $rt_mls ) && $rt_vessel == $converted_vessel_id && $rt_mls == $converted_mls_id ) {
                            echo '<p style="color: #46b450; font-weight: bold;">✅ Round-trip successful! Conversions are consistent.</p>';
                        } else {
                            echo '<p style="color: #ff9800; font-weight: bold;">⚠️ Round-trip inconsistent. Check conversion endpoints.</p>';
                        }
                    }
                } else {
                    echo '<p style="color: #666;">ℹ️ Skipping round-trip test (one or both conversions failed)</p>';
                }
                
                // Summary
                echo '<h4>Summary</h4>';
                echo '<div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">';
                echo '<p><strong>Original ID:</strong> ' . esc_html( $test_id ) . '</p>';
                if ( $converted_vessel_id ) {
                    echo '<p><strong>✅ Vessel ID:</strong> ' . esc_html( $converted_vessel_id ) . '</p>';
                } else {
                    echo '<p><strong>❌ Vessel ID:</strong> Conversion failed</p>';
                }
                if ( $converted_mls_id ) {
                    echo '<p><strong>✅ MLS ID:</strong> ' . esc_html( $converted_mls_id ) . '</p>';
                } else {
                    echo '<p><strong>❌ MLS ID:</strong> Conversion failed</p>';
                }
                echo '</div>';
                
                // Individual JSON Results for Each Test
                echo '<h4>Individual Test Results (JSON Format)</h4>';
                
                // Test 1 JSON
                echo '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin: 15px 0;">';
                echo '<p style="margin: 0 0 10px 0; font-weight: bold;">📋 Test 1: MLS ID → Vessel ID (JSON)</p>';
                $test1_json = array(
                    'test' => 'MLS ID → Vessel ID',
                    'original_id' => $test_id,
                    'endpoint' => $mls_to_vessel_endpoint,
                    'http_code' => $mls_to_vessel_code,
                    'success' => ! is_wp_error( $vessel_id_result ),
                    'converted_value' => $converted_vessel_id,
                    'error' => is_wp_error( $vessel_id_result ) ? $vessel_id_result->get_error_message() : null,
                    'raw_response' => $mls_to_vessel_body ?: ( is_wp_error( $mls_to_vessel_response ) ? $mls_to_vessel_response->get_error_message() : 'No response body' ),
                    'raw_response_json' => json_decode( $mls_to_vessel_body ?: '{}', true ),
                );
                echo '<pre style="background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; max-height: 400px; overflow-y: auto; font-size: 11px; margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . esc_html( json_encode( $test1_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
                echo '</div>';
                
                // Test 2 JSON
                echo '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin: 15px 0;">';
                echo '<p style="margin: 0 0 10px 0; font-weight: bold;">📋 Test 2: Vessel ID → MLS ID (JSON)</p>';
                $test2_json = array(
                    'test' => 'Vessel ID → MLS ID',
                    'original_id' => $test_id,
                    'endpoint' => $vessel_to_mls_endpoint,
                    'http_code' => $vessel_to_mls_code,
                    'success' => ! is_wp_error( $mls_id_result ),
                    'converted_value' => $converted_mls_id,
                    'error' => is_wp_error( $mls_id_result ) ? $mls_id_result->get_error_message() : null,
                    'raw_response' => $vessel_to_mls_body ?: ( is_wp_error( $vessel_to_mls_response ) ? $vessel_to_mls_response->get_error_message() : 'No response body' ),
                    'raw_response_json' => json_decode( $vessel_to_mls_body ?: '{}', true ),
                );
                echo '<pre style="background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; max-height: 400px; overflow-y: auto; font-size: 11px; margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . esc_html( json_encode( $test2_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
                echo '</div>';
                
                // Complete JSON Results for Sharing
                echo '<h4>Complete Test Results (Combined JSON - Copy to Share)</h4>';
                echo '<div style="background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; border-radius: 4px; margin: 15px 0;">';
                echo '<p style="margin: 0 0 10px 0; font-weight: bold; color: #1565c0;">📋 Copy the JSON below to share complete test results:</p>';
                $test_results_json = array(
                    'test_id' => $test_id,
                    'test_timestamp' => current_time( 'mysql' ),
                    'test_1_mls_to_vessel' => array(
                        'endpoint' => $mls_to_vessel_endpoint,
                        'http_code' => $mls_to_vessel_code,
                        'success' => ! is_wp_error( $vessel_id_result ),
                        'converted_value' => $converted_vessel_id,
                        'error' => is_wp_error( $vessel_id_result ) ? $vessel_id_result->get_error_message() : null,
                        'raw_response' => $mls_to_vessel_body ?: ( is_wp_error( $mls_to_vessel_response ) ? $mls_to_vessel_response->get_error_message() : 'No response body' ),
                        'raw_response_json' => json_decode( $mls_to_vessel_body ?: '{}', true ), // Parsed JSON if available
                    ),
                    'test_2_vessel_to_mls' => array(
                        'endpoint' => $vessel_to_mls_endpoint,
                        'http_code' => $vessel_to_mls_code,
                        'success' => ! is_wp_error( $mls_id_result ),
                        'converted_value' => $converted_mls_id,
                        'error' => is_wp_error( $mls_id_result ) ? $mls_id_result->get_error_message() : null,
                        'raw_response' => $vessel_to_mls_body ?: ( is_wp_error( $vessel_to_mls_response ) ? $vessel_to_mls_response->get_error_message() : 'No response body' ),
                        'raw_response_json' => json_decode( $vessel_to_mls_body ?: '{}', true ), // Parsed JSON if available
                    ),
                    'summary' => array(
                        'original_id' => $test_id,
                        'vessel_id' => $converted_vessel_id,
                        'mls_id' => $converted_mls_id,
                        'is_vessel_id' => ( $converted_mls_id && $converted_mls_id == $test_id ) ? true : false,
                        'is_mls_id' => ( $converted_vessel_id && $converted_vessel_id == $test_id ) ? true : false,
                    ),
                );
                echo '<pre style="background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; max-height: 500px; overflow-y: auto; font-size: 11px; margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . esc_html( json_encode( $test_results_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
                echo '</div>';
                
                echo '</div>';
            } else {
                echo '<div class="notice notice-error"><p>Please enter a valid ID (must be a positive number).</p></div>';
            }
        }
    }

    if ( isset( $_POST['yatco_test_vessel_data_only'] ) && ! empty( $_POST['yatco_test_vessel_data_only'] ) ) {
        if ( ! isset( $_POST['yatco_test_vessel_nonce'] ) || ! wp_verify_nonce( $_POST['yatco_test_vessel_nonce'], 'yatco_test_vessel' ) ) {
            echo '<div class="notice notice-error"><p>Security check failed. Please refresh the page and try again.</p></div>';
        } elseif ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            // Get vessel ID from form input or use first available
            $test_vessel_id = isset( $_POST['yatco_test_vessel_id'] ) && ! empty( $_POST['yatco_test_vessel_id'] ) ? intval( $_POST['yatco_test_vessel_id'] ) : null;
            
            echo '<div class="notice notice-info" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; margin: 15px 0;">';
            if ( $test_vessel_id ) {
                echo '<p><strong>🔍 Test Mode - Fetching & Importing Vessel ID: ' . esc_html( $test_vessel_id ) . '</strong></p>';
            } else {
                echo '<p><strong>🔍 Test Mode - Fetching & Importing First Available Vessel</strong></p>';
            }
            echo '<p>This will fetch vessel data from the YATCO API, display it below, and <strong>create a CPT post</strong> so you can see how the template renders.</p>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
            echo '<h3>Step 1: Getting Vessel Data</h3>';
            
            $found_vessel_id = null;
            $fullspecs = null;
            $response = null;
            $tried_vessels = array();
            
            if ( $test_vessel_id ) {
                // Use the provided vessel ID
                echo '<p><strong>Using provided vessel ID:</strong> ' . esc_html( $test_vessel_id ) . '</p>';
                $found_vessel_id = $test_vessel_id;
                $tried_vessels[] = $test_vessel_id;
                
                // Fetch data for the provided vessel ID - try FullSpecsAll first, then fallback to basic details
                echo '<h3>Step 2: Fetching Vessel Data for ID ' . esc_html( $test_vessel_id ) . '</h3>';
                
                // First try FullSpecsAll
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-api.php';
                $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $test_vessel_id ) . '/Details/FullSpecsAll';
                echo '<p style="color: #666; font-size: 13px;">Trying FullSpecsAll endpoint: <code>' . esc_html( $endpoint ) . '</code></p>';
                
                $fullspecs = yatco_fetch_fullspecs( $token, $test_vessel_id );
                
                if ( is_wp_error( $fullspecs ) && $fullspecs->get_error_code() !== 'import_stopped' ) {
                    // FullSpecsAll failed, try basic details as fallback
                    echo '<p style="color: #ff9800;">⚠️ FullSpecsAll endpoint failed: ' . esc_html( $fullspecs->get_error_message() ) . '</p>';
                    echo '<p style="color: #666; font-size: 13px;">Trying basic vessel endpoint as fallback...</p>';
                    
                    $fullspecs = yatco_fetch_basic_details( $token, $test_vessel_id );
                    
                    if ( is_wp_error( $fullspecs ) ) {
                        echo '<p style="color: #dc3232;">❌ Both FullSpecsAll and basic endpoints failed: ' . esc_html( $fullspecs->get_error_message() ) . '</p>';
                        $fullspecs = null;
                    } else {
                        echo '<p style="color: #46b450; font-weight: bold;">✅ Successfully retrieved basic vessel data for Vessel ID ' . esc_html( $test_vessel_id ) . ' (fallback)</p>';
                    }
                } elseif ( $fullspecs === null || ( is_array( $fullspecs ) && empty( $fullspecs ) ) ) {
                    // FullSpecsAll returned null, try basic details
                    echo '<p style="color: #ff9800;">⚠️ FullSpecsAll returned null for vessel ID ' . esc_html( $test_vessel_id ) . '</p>';
                    echo '<p style="color: #666; font-size: 13px;">Trying basic vessel endpoint as fallback...</p>';
                    
                    $fullspecs = yatco_fetch_basic_details( $token, $test_vessel_id );
                    
                    if ( is_wp_error( $fullspecs ) ) {
                        echo '<p style="color: #dc3232;">❌ Basic vessel endpoint also failed: ' . esc_html( $fullspecs->get_error_message() ) . '</p>';
                        $fullspecs = null;
                    } else {
                        echo '<p style="color: #46b450; font-weight: bold;">✅ Successfully retrieved basic vessel data for Vessel ID ' . esc_html( $test_vessel_id ) . ' (fallback)</p>';
                    }
                } else {
                    echo '<p style="color: #46b450; font-weight: bold;">✅ Successfully retrieved FullSpecsAll data for Vessel ID ' . esc_html( $test_vessel_id ) . '</p>';
                }
            } else {
                // Get multiple vessel IDs so we can try different ones if the first doesn't have FullSpecsAll data
                echo '<p>No vessel ID provided. Fetching first available vessel ID...</p>';
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
                }
                }
            }
            
            // Check if we successfully got a vessel with data (for both provided ID and auto-found ID)
            if ( ! $found_vessel_id || ! $fullspecs ) {
                echo '<div class="notice notice-error" style="background: #fce8e6; border-left: 4px solid #dc3232; padding: 15px; margin: 20px 0;">';
                echo '<p style="font-size: 16px; font-weight: bold; margin: 0 0 10px 0;"><strong>❌ No Accessible Vessel Data Found</strong></p>';
                if ( $test_vessel_id ) {
                    echo '<p>Vessel ID ' . esc_html( $test_vessel_id ) . ' does not have accessible FullSpecsAll data. This may indicate:</p>';
                } else {
                    echo '<p>Tried ' . count( $tried_vessels ) . ' vessel ID(s): <code>' . esc_html( implode( ', ', $tried_vessels ) ) . '</code></p>';
                    echo '<p>None of these vessels have accessible FullSpecsAll data. This may indicate:</p>';
                }
                echo '<ul style="margin-left: 20px; margin-top: 10px;">';
                echo '<li>Your API token may not have permissions to access FullSpecsAll data</li>';
                echo '<li>The vessels may have been removed or are restricted</li>';
                echo '<li>There may be a temporary API issue</li>';
                echo '</ul>';
                echo '<p style="margin-top: 15px;">Please verify your API token permissions or contact YATCO support.</p>';
                echo '</div>';
            } else {
                // Found a vessel with accessible FullSpecsAll data - proceed with import
                if ( ! isset( $response ) || is_wp_error( $response ) ) {
                    // If we don't have a response yet (for provided vessel ID), fetch it now
                    echo '<h3>Step 2: Fetching Vessel Data for ID ' . esc_html( $found_vessel_id ) . '</h3>';
                    
                    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $found_vessel_id ) . '/Details/FullSpecsAll';
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
                    
                    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                        $response_body = wp_remote_retrieve_body( $response );
                        $fullspecs = json_decode( $response_body, true );
                        if ( json_last_error() === JSON_ERROR_NONE && $fullspecs !== null && ! empty( $fullspecs ) ) {
                            echo '<p style="color: #46b450; font-weight: bold;">✅ Successfully retrieved FullSpecsAll data for Vessel ID ' . esc_html( $found_vessel_id ) . '</p>';
                        }
                    }
                }
                
                // Display response info if we have it
                if ( isset( $response ) && ! is_wp_error( $response ) ) {
                    echo '<p><strong>Response Code:</strong> ' . esc_html( wp_remote_retrieve_response_code( $response ) ) . '</p>';
                    echo '<p><strong>Content-Type:</strong> ' . esc_html( wp_remote_retrieve_header( $response, 'content-type' ) ) . '</p>';
                    echo '<p><strong>Response Length:</strong> ' . strlen( wp_remote_retrieve_body( $response ) ) . ' characters</p>';
                    echo '<p><strong>✅ Success!</strong> FullSpecsAll data retrieved and parsed successfully.</p>';
                    
                    if ( is_array( $fullspecs ) && ! empty( $fullspecs ) ) {
                        $sections_found = array_keys( $fullspecs );
                        echo '<p style="color: #666; font-size: 13px;">Data sections found: <strong>' . esc_html( count( $sections_found ) ) . '</strong> sections (' . esc_html( implode( ', ', array_slice( $sections_found, 0, 5 ) ) ) . ( count( $sections_found ) > 5 ? ', ...' : '' ) . ')</p>';
                        
                        // Extract and display price information
                        $result = isset( $fullspecs['Result'] ) ? $fullspecs['Result'] : array();
                        $basic  = isset( $fullspecs['BasicInfo'] ) ? $fullspecs['BasicInfo'] : array();
                        
                        $price_formatted = '';
                        if ( isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ) {
                            $price_formatted = '$' . number_format( floatval( $basic['AskingPriceUSD'] ), 0 );
                        } elseif ( isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
                            $currency = isset( $basic['Currency'] ) ? $basic['Currency'] : ( isset( $result['Currency'] ) ? $result['Currency'] : 'USD' );
                            if ( isset( $result['AskingPriceFormatted'] ) && ! empty( $result['AskingPriceFormatted'] ) ) {
                                $price_formatted = $result['AskingPriceFormatted'];
                            } else {
                                $price_formatted = $currency . ' ' . number_format( floatval( $result['AskingPriceCompare'] ), 0 );
                            }
                        } elseif ( isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 ) {
                            $currency = isset( $basic['Currency'] ) ? $basic['Currency'] : 'USD';
                            $price_formatted = $currency . ' ' . number_format( floatval( $basic['AskingPrice'] ), 0 );
                        }
                        
                        // Check for "Price on Application"
                        if ( isset( $result['PriceOnApplication'] ) && $result['PriceOnApplication'] ) {
                            $price_formatted = 'Price on Application';
                        } elseif ( isset( $basic['PriceOnApplication'] ) && $basic['PriceOnApplication'] ) {
                            $price_formatted = 'Price on Application';
                        }
                        
                        if ( ! empty( $price_formatted ) ) {
                            echo '<p style="color: #666; font-size: 14px; margin-top: 10px;"><strong>💰 Price:</strong> <span style="font-size: 16px; color: #2271b1; font-weight: bold;">' . esc_html( $price_formatted ) . '</span></p>';
                        }
                    }
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
                    
                    echo '<h3 style="margin-top: 30px;">Full API Response</h3>';
                    echo '<p style="color: #666; font-size: 13px; margin-bottom: 15px;">View and search the complete JSON response from the YATCO API:</p>';
                    
                    // Store fullspecs JSON for JavaScript access
                    $fullspecs_json = wp_json_encode( $fullspecs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                    
                    echo '<div style="margin-bottom: 15px;">';
                    echo '<button type="button" id="yatco-toggle-full-api" class="button button-secondary" style="margin-right: 10px;">📋 View Full API</button>';
                    echo '<input type="text" id="yatco-api-search" placeholder="Search API response (Ctrl+F also works)..." class="regular-text" style="width: 350px; display: none;" />';
                    echo '</div>';
                    
                    echo '<div id="yatco-full-api-display" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; border-radius: 4px; padding: 20px; max-height: 700px; overflow: auto; font-family: "Courier New", Courier, monospace; font-size: 13px; line-height: 1.6; display: none; position: relative;">';
                    echo '<pre id="yatco-api-content" style="margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #d4d4d4;">';
                    echo esc_html( $fullspecs_json );
                    echo '</pre>';
                    echo '</div>';
                    
                    // JavaScript for toggle and search functionality
                    echo '<script type="text/javascript">';
                    echo 'jQuery(document).ready(function($) {';
                    echo '    var $toggleBtn = $("#yatco-toggle-full-api");';
                    echo '    var $searchBox = $("#yatco-api-search");';
                    echo '    var $apiDisplay = $("#yatco-full-api-display");';
                    echo '    var $apiContent = $("#yatco-api-content");';
                    echo '    var originalContent = ' . wp_json_encode( $fullspecs_json ) . ';';
                    echo '    var isExpanded = false;';
                    echo '    ';
                    echo '    // Toggle button functionality';
                    echo '    $toggleBtn.on("click", function() {';
                    echo '        if (isExpanded) {';
                    echo '            $apiDisplay.slideUp(300);';
                    echo '            $searchBox.slideUp(200);';
                    echo '            $toggleBtn.text("📋 View Full API");';
                    echo '            isExpanded = false;';
                    echo '            $searchBox.val("");';
                    echo '            $apiContent.text(originalContent);';
                    echo '        } else {';
                    echo '            $apiDisplay.slideDown(300);';
                    echo '            $searchBox.slideDown(200);';
                    echo '            $toggleBtn.text("🔽 Hide Full API");';
                    echo '            isExpanded = true;';
                    echo '            $searchBox.focus();';
                    echo '        }';
                    echo '    });';
                    echo '    ';
                    echo '    // Search functionality with highlighting';
                    echo '    var searchTimeout;';
                    echo '    $searchBox.on("input keyup", function(e) {';
                    echo '        // Allow Ctrl+F to work naturally';
                    echo '        if (e.ctrlKey && e.key === "f") {';
                    echo '            return;';
                    echo '        }';
                    echo '        ';
                    echo '        var searchTerm = $(this).val();';
                    echo '        clearTimeout(searchTimeout);';
                    echo '        ';
                    echo '        if (searchTerm === "") {';
                    echo '            $apiContent.text(originalContent);';
                    echo '            return;';
                    echo '        }';
                    echo '        ';
                    echo '        searchTimeout = setTimeout(function() {';
                    echo '            var regex = new RegExp("(" + searchTerm.replace(/[.*+?^${}()|[\\]\\\\]/g, "\\\\$&") + ")", "gi");';
                    echo '            var highlightedContent = originalContent.replace(regex, "<mark style=\'background: #ffeb3b; color: #000; padding: 2px 4px; border-radius: 2px;\'>$1</mark>");';
                    echo '            $apiContent.html(highlightedContent);';
                    echo '            ';
                    echo '            // Scroll to first match';
                    echo '            var firstMark = $apiContent.find("mark").first();';
                    echo '            if (firstMark.length) {';
                    echo '                $apiDisplay.animate({';
                    echo '                    scrollTop: firstMark.offset().top - $apiDisplay.offset().top + $apiDisplay.scrollTop() - 100';
                    echo '                }, 300);';
                    echo '            }';
                    echo '        }, 300);';
                    echo '    });';
                    echo '});';
                    echo '</script>';
                }
            }
            
            echo '</div>'; // Close Step 1 div
        }
        
        // Browse Vessel API Section
        echo '<hr style="margin: 40px 0;" />';
        echo '<h2>Browse Single Vessel API</h2>';
        echo '<div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 12px; margin: 15px 0;">';
        echo '<p style="margin: 0; font-weight: bold; color: #004085;"><strong>🔍 Browse API:</strong> View the full API response for any vessel by entering its Vessel ID. This is useful for exploring the API structure and finding specific data fields.</p>';
        echo '</div>';
        echo '<p>Enter a YATCO Vessel ID to view its complete API response without creating a post.</p>';
        echo '<form method="post" id="yatco-browse-vessel-form">';
        wp_nonce_field( 'yatco_browse_vessel', 'yatco_browse_vessel_nonce' );
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="yatco_browse_vessel_id">Vessel ID</label></th>';
        echo '<td><input type="text" id="yatco_browse_vessel_id" name="yatco_browse_vessel_id" value="' . ( isset( $_POST['yatco_browse_vessel_id'] ) ? esc_attr( $_POST['yatco_browse_vessel_id'] ) : '' ) . '" class="regular-text" placeholder="e.g., 413131" required />';
        echo '<p class="description">Enter a specific YATCO Vessel ID to view its full API response.</p></td>';
        echo '</tr>';
        echo '</table>';
        submit_button( '🔍 Fetch & View Full API', 'primary', 'yatco_browse_vessel', false, array( 'id' => 'yatco-browse-vessel-btn' ) );
        echo '</form>';
        
        if ( isset( $_POST['yatco_browse_vessel'] ) && check_admin_referer( 'yatco_browse_vessel', 'yatco_browse_vessel_nonce' ) ) {
            $browse_vessel_id = isset( $_POST['yatco_browse_vessel_id'] ) ? intval( $_POST['yatco_browse_vessel_id'] ) : 0;
            
            if ( empty( $browse_vessel_id ) ) {
                echo '<div class="notice notice-error" style="margin-top: 15px;"><p>Please enter a valid Vessel ID.</p></div>';
            } elseif ( empty( $token ) ) {
                echo '<div class="notice notice-error" style="margin-top: 15px;"><p>Missing API token. Please configure your API token first.</p></div>';
            } else {
                echo '<div class="notice notice-info" style="margin-top: 15px;"><p><strong>Fetching API data for Vessel ID: ' . esc_html( $browse_vessel_id ) . '</strong></p></div>';
                
                // Fetch full specs
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-api.php';
                $fullspecs = yatco_fetch_fullspecs( $token, $browse_vessel_id );
                
                if ( is_wp_error( $fullspecs ) ) {
                    echo '<div class="notice notice-error" style="margin-top: 15px;">';
                    echo '<p><strong>Error:</strong> ' . esc_html( $fullspecs->get_error_message() ) . '</p>';
                    echo '</div>';
                    
                    // Try basic details as fallback
                    $basic_details = yatco_fetch_basic_details( $token, $browse_vessel_id );
                    if ( ! is_wp_error( $basic_details ) && ! empty( $basic_details ) ) {
                        echo '<div class="notice notice-warning" style="margin-top: 15px;">';
                        echo '<p><strong>FullSpecsAll not available, but basic details were found. Displaying basic details:</strong></p>';
                        echo '</div>';
                        $fullspecs = $basic_details;
                    }
                }
                
                if ( ! is_wp_error( $fullspecs ) && ! empty( $fullspecs ) ) {
                    // Extract vessel info for display
                    $result = isset( $fullspecs['Result'] ) ? $fullspecs['Result'] : array();
                    $basic  = isset( $fullspecs['BasicInfo'] ) ? $fullspecs['BasicInfo'] : array();
                    
                    // Vessel name
                    $vessel_name = '';
                    if ( ! empty( $basic['BoatName'] ) ) {
                        $vessel_name = $basic['BoatName'];
                    } elseif ( ! empty( $result['VesselName'] ) ) {
                        $vessel_name = $result['VesselName'];
                    } elseif ( ! empty( $result['BoatName'] ) ) {
                        $vessel_name = $result['BoatName'];
                    }
                    
                    // Price: Prefer USD price from BasicInfo, fallback to Result.AskingPriceCompare
                    $price = '';
                    $price_formatted = '';
                    $currency = 'USD';
                    
                    if ( isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ) {
                        $price = floatval( $basic['AskingPriceUSD'] );
                        $price_formatted = '$' . number_format( $price, 0 );
                    } elseif ( isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
                        $price = floatval( $result['AskingPriceCompare'] );
                        $currency = isset( $basic['Currency'] ) ? $basic['Currency'] : ( isset( $result['Currency'] ) ? $result['Currency'] : 'USD' );
                        if ( isset( $result['AskingPriceFormatted'] ) && ! empty( $result['AskingPriceFormatted'] ) ) {
                            $price_formatted = $result['AskingPriceFormatted'];
                        } else {
                            $price_formatted = $currency . ' ' . number_format( $price, 0 );
                        }
                    } elseif ( isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 ) {
                        $price = floatval( $basic['AskingPrice'] );
                        $currency = isset( $basic['Currency'] ) ? $basic['Currency'] : 'USD';
                        $price_formatted = $currency . ' ' . number_format( $price, 0 );
                    }
                    
                    // Check for "Price on Application"
                    $price_on_application = false;
                    if ( isset( $result['PriceOnApplication'] ) && $result['PriceOnApplication'] ) {
                        $price_on_application = true;
                        $price_formatted = 'Price on Application';
                    } elseif ( isset( $basic['PriceOnApplication'] ) && $basic['PriceOnApplication'] ) {
                        $price_on_application = true;
                        $price_formatted = 'Price on Application';
                    }
                    
                    echo '<div style="background: #d4edda; border-left: 4px solid #46b450; padding: 15px; margin-top: 15px;">';
                    echo '<p style="margin: 0 0 8px 0; font-weight: bold; color: #155724; font-size: 16px;">✅ Successfully fetched API data for Vessel ID: ' . esc_html( $browse_vessel_id );
                    if ( ! empty( $vessel_name ) ) {
                        echo ' (' . esc_html( $vessel_name ) . ')';
                    }
                    echo '</p>';
                    if ( ! empty( $price_formatted ) ) {
                        echo '<p style="margin: 0; color: #155724; font-size: 18px; font-weight: bold;">💰 Price: ' . esc_html( $price_formatted ) . '</p>';
                    }
                    echo '</div>';
                    
                    // Store fullspecs JSON for JavaScript access
                    $fullspecs_json = wp_json_encode( $fullspecs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                    
                    echo '<h3 style="margin-top: 30px;">Full API Response</h3>';
                    echo '<p style="color: #666; font-size: 13px; margin-bottom: 15px;">View and search the complete JSON response from the YATCO API:</p>';
                    
                    echo '<div style="margin-bottom: 15px;">';
                    echo '<button type="button" id="yatco-browse-toggle-full-api" class="button button-secondary" style="margin-right: 10px;">📋 View Full API</button>';
                    echo '<input type="text" id="yatco-browse-api-search" placeholder="Search API response (Ctrl+F also works)..." class="regular-text" style="width: 350px; display: none;" />';
                    echo '</div>';
                    
                    echo '<div id="yatco-browse-full-api-display" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; border-radius: 4px; padding: 20px; max-height: 700px; overflow: auto; font-family: "Courier New", Courier, monospace; font-size: 13px; line-height: 1.6; display: none; position: relative;">';
                    echo '<pre id="yatco-browse-api-content" style="margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #d4d4d4;">';
                    echo esc_html( $fullspecs_json );
                    echo '</pre>';
                    echo '</div>';
                    
                    // JavaScript for toggle and search functionality
                    echo '<script type="text/javascript">';
                    echo 'jQuery(document).ready(function($) {';
                    echo '    var $toggleBtn = $("#yatco-browse-toggle-full-api");';
                    echo '    var $searchBox = $("#yatco-browse-api-search");';
                    echo '    var $apiDisplay = $("#yatco-browse-full-api-display");';
                    echo '    var $apiContent = $("#yatco-browse-api-content");';
                    echo '    var originalContent = ' . wp_json_encode( $fullspecs_json ) . ';';
                    echo '    var isExpanded = false;';
                    echo '    ';
                    echo '    // Toggle button functionality';
                    echo '    $toggleBtn.on("click", function() {';
                    echo '        if (isExpanded) {';
                    echo '            $apiDisplay.slideUp(300);';
                    echo '            $searchBox.slideUp(200);';
                    echo '            $toggleBtn.text("📋 View Full API");';
                    echo '            isExpanded = false;';
                    echo '            $searchBox.val("");';
                    echo '            $apiContent.text(originalContent);';
                    echo '        } else {';
                    echo '            $apiDisplay.slideDown(300);';
                    echo '            $searchBox.slideDown(200);';
                    echo '            $toggleBtn.text("🔽 Hide Full API");';
                    echo '            isExpanded = true;';
                    echo '            $searchBox.focus();';
                    echo '        }';
                    echo '    });';
                    echo '    ';
                    echo '    // Search functionality with highlighting';
                    echo '    var searchTimeout;';
                    echo '    $searchBox.on("input keyup", function(e) {';
                    echo '        // Allow Ctrl+F to work naturally';
                    echo '        if (e.ctrlKey && e.key === "f") {';
                    echo '            return;';
                    echo '        }';
                    echo '        ';
                    echo '        var searchTerm = $(this).val();';
                    echo '        clearTimeout(searchTimeout);';
                    echo '        ';
                    echo '        if (searchTerm === "") {';
                    echo '            $apiContent.text(originalContent);';
                    echo '            return;';
                    echo '        }';
                    echo '        ';
                    echo '        searchTimeout = setTimeout(function() {';
                    echo '            var regex = new RegExp("(" + searchTerm.replace(/[.*+?^${}()|[\\]\\\\]/g, "\\\\$&") + ")", "gi");';
                    echo '            var highlightedContent = originalContent.replace(regex, "<mark style=\'background: #ffeb3b; color: #000; padding: 2px 4px; border-radius: 2px;\'>$1</mark>");';
                    echo '            $apiContent.html(highlightedContent);';
                    echo '            ';
                    echo '            // Scroll to first match';
                    echo '            var firstMark = $apiContent.find("mark").first();';
                    echo '            if (firstMark.length) {';
                    echo '                $apiDisplay.animate({';
                    echo '                    scrollTop: firstMark.offset().top - $apiDisplay.offset().top + $apiDisplay.scrollTop() - 100';
                    echo '                }, 300);';
                    echo '            }';
                    echo '        }, 300);';
                    echo '    });';
                    echo '});';
                    echo '</script>';
                } else {
                    echo '<div class="notice notice-error" style="margin-top: 15px;">';
                    echo '<p><strong>Error:</strong> Unable to fetch API data for Vessel ID ' . esc_html( $browse_vessel_id ) . '. The vessel may not exist or may not be accessible.</p>';
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
    
    echo '<h3>System Status</h3>';
    echo '<table class="widefat" style="margin-bottom: 20px;">';
    echo '<tr><th style="text-align: left; width: 250px;">Check</th><th style="text-align: left;">Status</th></tr>';
    
    echo '<tr><td><strong>Update All Vessels Function:</strong></td><td>';
    if ( function_exists( 'yatco_warm_cache_function' ) ) {
        echo '<span style="color: #46b450;">✔ Available</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Available</span>';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>Hooks Registered:</strong></td><td>';
    $hooks_registered = array();
    global $wp_filter;
    // Check if hooks are registered using multiple methods for reliability
    if ( isset( $wp_filter ) && is_object( $wp_filter ) ) {
        if ( isset( $wp_filter->callbacks['yatco_warm_cache_hook'] ) || isset( $wp_filter['yatco_warm_cache_hook'] ) ) {
            $hooks_registered[] = 'yatco_warm_cache_hook';
        }
        if ( isset( $wp_filter->callbacks['yatco_full_import_hook'] ) || isset( $wp_filter['yatco_full_import_hook'] ) ) {
            $hooks_registered[] = 'yatco_full_import_hook';
        }
        if ( isset( $wp_filter->callbacks['yatco_daily_sync_hook'] ) || isset( $wp_filter['yatco_daily_sync_hook'] ) ) {
            $hooks_registered[] = 'yatco_daily_sync_hook';
        }
    }
    // Alternative check: verify functions exist (hooks are registered in yatco-custom-integration.php)
    if ( empty( $hooks_registered ) ) {
        // Check if the hook registration file is loaded by checking if functions exist
        if ( has_action( 'yatco_warm_cache_hook' ) || function_exists( 'yatco_warm_cache_function' ) ) {
            $hooks_registered[] = 'yatco_warm_cache_hook';
        }
        if ( has_action( 'yatco_full_import_hook' ) ) {
            $hooks_registered[] = 'yatco_full_import_hook';
        }
        if ( has_action( 'yatco_daily_sync_hook' ) ) {
            $hooks_registered[] = 'yatco_daily_sync_hook';
        }
    }
    if ( ! empty( $hooks_registered ) ) {
        echo '<span style="color: #46b450;">✔ Registered: ' . esc_html( implode( ', ', $hooks_registered ) ) . '</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ No hooks registered</span>';
        echo '<br /><span style="color: #666; font-size: 12px;">Note: Hooks are required for server cron to trigger scheduled events. If you see "No hooks registered", check that the plugin files are loaded correctly.</span>';
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
        $scheduled_events[] = 'Update All Vessels: ' . date( 'Y-m-d H:i:s', $scheduled_warm );
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
                echo '<div class="notice notice-success"><p>✅ Update All Vessels function is available.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Update All Vessels function is NOT available. Check if yatco-cache.php is loaded.</p></div>';
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
    
    echo '<h3>Update All Vessels (Manual)</h3>';
    echo '<p>Update and sync all vessels from the YATCO API. This will fetch the latest data for all active vessels and update existing posts or create new ones. This will block until complete.</p>';
    
    echo '<form method="post" style="margin-bottom: 15px;">';
    wp_nonce_field( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' );
    submit_button( 'Update All Vessels NOW', 'primary large', 'yatco_manual_trigger', false, array( 'style' => 'font-size: 14px; padding: 8px 16px; height: auto;' ) );
    echo '</form>';
    
    if ( isset( $_POST['yatco_manual_trigger'] ) && check_admin_referer( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token.</p></div>';
        } else {
            if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';
            }
            
            set_transient( 'yatco_cache_warming_status', 'Starting vessel update...', 600 );
            set_transient( 'yatco_cache_warming_progress', array( 'last_processed' => 0, 'total' => 0, 'timestamp' => time() ), 600 );
            
            echo '<div class="notice notice-info">';
            echo '<p><strong>Starting vessel update...</strong></p>';
            echo '<p>This will fetch and update all vessels from the YATCO API. This will run synchronously (blocking) and may take several minutes. <strong>Do not close this page.</strong></p>';
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
            echo '<p><strong>Vessel update completed!</strong></p>';
            if ( $final_progress !== false && is_array( $final_progress ) ) {
                $processed = isset( $final_progress['processed'] ) ? intval( $final_progress['processed'] ) : 0;
                echo '<p>Total vessels updated: <strong>' . number_format( $processed ) . '</strong></p>';
            }
            echo '</div>';
            
            $is_running = false;
        }
    }
    
    echo '<h3>Server Cron Setup (Required for Auto-Resume & Auto-Update)</h3>';
    echo '<div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 10px 0;">';
    echo '<p>Set up a server cron job to enable auto-resume and automatic vessel updates. <strong>This is required if you want these features to work automatically.</strong></p>';
    echo '<div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 10px; margin: 15px 0;">';
    echo '<p style="margin: 5px 0; font-weight: bold;">ℹ️ What the Server Cron Does:</p>';
    echo '<ul style="margin: 5px 0 0 20px; padding-left: 10px; font-size: 13px;">';
    echo '<li><strong>Helps with auto-resume</strong> - If an import times out, the server cron can help resume it automatically (if auto-resume is enabled)</li>';
    echo '<li><strong>Runs automatic vessel updates</strong> - If "Auto-Update Vessels" is enabled in settings, the server cron will update all vessels every 6 hours</li>';
    echo '<li><strong>Does NOT start new imports</strong> - You must manually click "Run Full Import" or "Run Daily Sync" buttons to start imports</li>';
    echo '<li><strong>Recommended frequency:</strong> Every 5 minutes (<code>*/5 * * * *</code>) - This ensures scheduled events run promptly</li>';
    echo '</ul>';
    echo '<p style="margin: 5px 0; font-size: 13px;"><strong>Note:</strong> The numbers in cron schedules (like <code>*/5</code>, <code>*/15</code>) represent minutes. <code>*/5</code> means every 5 minutes, <code>*/15</code> means every 15 minutes, etc.</p>';
    echo '</div>';
    
    echo '<p style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 10px; margin: 15px 0;"><strong>💡 Recommended:</strong> Use <strong>Option 1 (Direct PHP)</strong> - it\'s the most reliable method and works even if <code>wp-cron.php</code> is not accessible via HTTP.</p>';
    
    echo '<p style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0;"><strong>⚠️ Note:</strong> If you\'re using WP-CLI (<code>wp cron event run --due-now</code>), make sure WP-CLI is installed and accessible from your cron job.</p>';
    
    echo '<p><strong>Option 1: Direct PHP Execution (RECOMMENDED - Works even if wp-cron.php returns 404)</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/5 * * * * /usr/bin/php -q ' . esc_html( ABSPATH ) . 'wp-cron.php > /dev/null 2>&1</pre>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">This runs every 5 minutes. Change <code>*/5</code> to <code>*/15</code> for every 15 minutes, or <code>0 2 * * *</code> for daily at 2 AM.</p>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">Common PHP paths: <code>/usr/bin/php</code>, <code>/usr/local/bin/php</code>, <code>/opt/cpanel/ea-php81/root/usr/bin/php</code> (cPanel), or <code>php</code> (if in PATH)</p>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">To find PHP path, run: <code>which php</code> or <code>whereis php</code> on your server</p>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;"><strong>Note:</strong> The <code>-q</code> flag runs PHP in quiet mode (suppresses HTTP headers), perfect for cron jobs.</p>';
    
    echo '<p><strong>Option 2: Using WP-CLI (Alternative if wp-cron.php returns 404)</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/5 * * * * cd ' . esc_html( ABSPATH ) . ' && /usr/local/bin/wp cron event run --due-now > /dev/null 2>&1</pre>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">Common WP-CLI paths: <code>/usr/local/bin/wp</code>, <code>/usr/bin/wp</code>, <code>/opt/cpanel/ea-php81/root/usr/bin/wp</code>, or <code>wp</code> (if in PATH)</p>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">To find WP-CLI path, run: <code>which wp</code> or <code>whereis wp</code> on your server</p>';
    
    echo '<p><strong>Option 3: Using curl (Only if wp-cron.php is accessible via HTTP - Not Recommended)</strong></p>';
    echo '<p style="font-size: 12px; color: #dc3232; margin: 5px 0;"><strong>⚠️ Warning:</strong> This method may fail if <code>wp-cron.php</code> is not accessible via HTTP (common with subdomains or URL rewriting). Use Option 1 instead.</p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/5 * * * * curl -s ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</pre>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;"><strong>If curl doesn\'t work, try with full path:</strong> <code>*/5 * * * * /usr/bin/curl -s ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</code></p>';
    
    echo '<p><strong>Option 4: Using wget (Only if wp-cron.php is accessible via HTTP - Not Recommended)</strong></p>';
    echo '<p style="font-size: 12px; color: #dc3232; margin: 5px 0;"><strong>⚠️ Warning:</strong> This method may fail if <code>wp-cron.php</code> is not accessible via HTTP. Use Option 1 instead.</p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/5 * * * * wget -q -O - ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</pre>';
    
    echo '<p style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 10px; margin: 15px 0;"><strong>💡 Tip:</strong> After setting up the cron job, wait a few minutes and check the "Last Status" above to see if scheduled events are running. You can also check your server\'s cron logs to verify the job is executing.</p>';
    
    echo '<h4 style="margin-top: 20px; margin-bottom: 10px;">🔧 Troubleshooting Cron Issues</h4>';
    echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0;">';
    echo '<p><strong>Common Issues:</strong></p>';
    echo '<ol style="margin-left: 20px; padding-left: 10px;">';
    echo '<li><strong>Use SPACES, not TABS:</strong> Crontab requires spaces between fields. Your entry should look like:<br />';
    echo '<code style="background: #f5f5f5; padding: 2px 5px;">*/5 * * * * curl -s ...</code> (spaces between each field)</li>';
    echo '<li><strong>Use Direct PHP (Recommended):</strong> Instead of curl/wget, use direct PHP execution which always works:<br />';
    echo '<code style="background: #f5f5f5; padding: 2px 5px;">*/5 * * * * /usr/bin/php -q ' . esc_html( ABSPATH ) . 'wp-cron.php > /dev/null 2>&1</code><br />';
    echo 'To find PHP path, run: <code>which php</code> or <code>whereis php</code> on your server</li>';
    echo '<li><strong>Test the command manually:</strong> SSH into your server and run:<br />';
    echo '<code style="background: #f5f5f5; padding: 2px 5px;">/usr/bin/php -q ' . esc_html( ABSPATH ) . 'wp-cron.php</code><br />';
    echo 'If this works, the issue is with the cron format or cron service.</li>';
    echo '<li><strong>Check cron logs:</strong> View cron execution logs with:<br />';
    echo '<code style="background: #f5f5f5; padding: 2px 5px;">grep CRON /var/log/syslog</code> (Linux) or check your hosting control panel\'s cron logs</li>';
    echo '<li><strong>Verify cron is running:</strong> Check if cron service is active:<br />';
    echo '<code style="background: #f5f5f5; padding: 2px 5px;">systemctl status cron</code> (systemd) or <code>service crond status</code> (init.d)</li>';
    echo '<li><strong>Alternative with full path:</strong> If curl path is unknown, use wget instead:<br />';
    echo '<code style="background: #f5f5f5; padding: 2px 5px;">*/5 * * * * /usr/bin/wget -q -O - ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</code></li>';
    echo '<li><strong>Getting 404 Error with curl/wget?</strong> If <code>wp-cron.php</code> returns a 404 page when accessed via HTTP, this is normal for subdomains or URL rewriting. <strong>Solution:</strong><br />';
    echo '<ul style="margin-top: 5px; margin-left: 20px;">';
    echo '<li><strong>Use Direct PHP Execution (Option 1):</strong> This always works regardless of HTTP access: <code style="background: #f5f5f5; padding: 2px 5px;">*/5 * * * * /usr/bin/php -q ' . esc_html( ABSPATH ) . 'wp-cron.php > /dev/null 2>&1</code></li>';
    echo '<li><strong>Alternative:</strong> Use WP-CLI (Option 2): <code style="background: #f5f5f5; padding: 2px 5px;">*/5 * * * * cd ' . esc_html( ABSPATH ) . ' && /usr/local/bin/wp cron event run --due-now > /dev/null 2>&1</code></li>';
    echo '<li>Find PHP path: Run <code>which php</code> or <code>whereis php</code> on your server</li>';
    echo '<li>Find WP-CLI path: Run <code>which wp</code> or <code>whereis wp</code> on your server</li>';
    echo '<li>Verify file exists: SSH and check <code>ls -la ' . esc_html( ABSPATH ) . 'wp-cron.php</code></li>';
    echo '</ul></li>';
    echo '</ol>';
    echo '</div>';
    
    echo '<h4 style="margin-top: 20px; margin-bottom: 10px;">💡 Why Use Direct PHP Execution?</h4>';
    echo '<div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 10px 0;">';
    echo '<p><strong>Direct PHP execution (Option 1) is the recommended method because:</strong></p>';
    echo '<ul style="margin-left: 20px; padding-left: 10px;">';
    echo '<li><strong>Always works</strong> - Doesn\'t depend on HTTP access to <code>wp-cron.php</code></li>';
    echo '<li><strong>Works with subdomains</strong> - No issues with document root configuration</li>';
    echo '<li><strong>Works with URL rewriting</strong> - Doesn\'t require HTTP access</li>';
    echo '<li><strong>More reliable</strong> - Direct file execution is faster and more secure</li>';
    echo '</ul>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;"><strong>Note:</strong> If you see 404 errors when testing <code>wp-cron.php</code> via HTTP (curl/wget), this is normal and doesn\'t affect server cron. Just use Option 1 (Direct PHP) for your cron job.</p>';
    echo '</div>';
    
    echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">Add this to your server\'s crontab using <code>crontab -e</code> (SSH) or through your hosting control panel (cPanel, Plesk, etc.).</p>';
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
 * AJAX handler to manually trigger update all vessels.
 */
function yatco_ajax_trigger_cache_warming() {
    check_ajax_referer( 'yatco_trigger_warming', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    delete_transient( 'yatco_cache_warming_progress' );
    delete_transient( 'yatco_cache_warming_status' );
    
    set_transient( 'yatco_cache_warming_status', 'Starting vessel update...', 600 );
    
    wp_schedule_single_event( time(), 'yatco_warm_cache_hook' );
    spawn_cron();
    
    wp_send_json_success( array( 'message' => 'Vessel update started' ) );
}
add_action( 'wp_ajax_yatco_trigger_cache_warming', 'yatco_ajax_trigger_cache_warming' );

/**
 * AJAX handler to run update all vessels directly (synchronous - for testing).
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
    
    set_transient( 'yatco_cache_warming_status', 'Starting vessel update...', 600 );
    yatco_warm_cache_function();
    
    wp_send_json_success( array( 'message' => 'Vessel update completed' ) );
}
add_action( 'wp_ajax_yatco_run_cache_warming_direct', 'yatco_ajax_run_cache_warming_direct' );

/**
 * AJAX handler to get update all vessels status and progress.
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