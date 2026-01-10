<?php
/**
 * File-based locking system to prevent OPcache contention
 * Uses filesystem locks instead of database locks to avoid FPM worker conflicts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Acquire a file-based lock for daily sync (prevents OPcache contention)
 * 
 * @param string $lock_name Lock identifier (e.g., 'daily_sync')
 * @param int $timeout Maximum time to wait for lock (seconds)
 * @return array|false Returns array with 'handle' and 'file' on success, false on failure
 */
function yatco_acquire_file_lock( $lock_name, $timeout = 30 ) {
    $lock_dir = get_temp_dir();
    if ( ! $lock_dir ) {
        $lock_dir = sys_get_temp_dir();
    }
    
    $lock_file = $lock_dir . '/yatco_' . $lock_name . '.lock';
    $handle = @fopen( $lock_file, 'c+' );
    
    if ( ! $handle ) {
        yatco_log( "File Lock: Failed to open lock file: {$lock_file}", 'error' );
        return false;
    }
    
    $start_time = time();
    $acquired = false;
    
    // Try to acquire lock with exponential backoff to prevent OPcache contention
    while ( time() - $start_time < $timeout ) {
        // Use flock with LOCK_EX | LOCK_NB (non-blocking exclusive lock)
        // This prevents busy loops - returns immediately if lock can't be acquired
        if ( @flock( $handle, LOCK_EX | LOCK_NB ) ) {
            // Lock acquired - write PID and timestamp
            $lock_data = array(
                'pid' => getmypid() ?: ( time() . rand( 1000, 9999 ) ),
                'timestamp' => time(),
                'lock_name' => $lock_name,
            );
            ftruncate( $handle, 0 );
            rewind( $handle );
            fwrite( $handle, json_encode( $lock_data ) );
            fflush( $handle );
            $acquired = true;
            break;
        }
        
        // Lock is held by another process - wait with exponential backoff
        // CRITICAL: Close handle BEFORE sleeping to prevent holding file descriptor
        // This prevents OPcache contention issues
        @flock( $handle, LOCK_UN );
        @fclose( $handle );
        $handle = null;
        
        $elapsed = time() - $start_time;
        $wait_time = min( pow( 2, floor( $elapsed / 5 ) ), 5 ); // Exponential backoff: 1s, 2s, 4s, 5s max
        yatco_log( "File Lock: Lock held by another process, waiting {$wait_time}s before retry (elapsed: {$elapsed}s)", 'debug' );
        
        // Sleep to avoid busy loop (CRITICAL for OPcache contention prevention)
        // This prevents the busy loop that causes server lockups
        sleep( $wait_time );
        
        // Reopen handle for next attempt
        $handle = @fopen( $lock_file, 'c+' );
        if ( ! $handle ) {
            yatco_log( "File Lock: Failed to reopen lock file after wait", 'error' );
            return false;
        }
        
        // Check if lock file is stale (older than 10 minutes)
        $lock_info = @fread( $handle, 1024 );
        if ( $lock_info ) {
            $lock_data = json_decode( $lock_info, true );
            if ( $lock_data && isset( $lock_data['timestamp'] ) ) {
                $lock_age = time() - intval( $lock_data['timestamp'] );
                if ( $lock_age > 600 ) {
                    // Stale lock - try to clear it
                    yatco_log( "File Lock: Detected stale lock (age: {$lock_age}s), attempting to clear", 'warning' );
                    @flock( $handle, LOCK_UN );
                    @fclose( $handle );
                    @unlink( $lock_file );
                    
                    // Try one more time
                    $handle = @fopen( $lock_file, 'c+' );
                    if ( $handle && @flock( $handle, LOCK_EX | LOCK_NB ) ) {
                        $lock_data = array(
                            'pid' => getmypid() ?: ( time() . rand( 1000, 9999 ) ),
                            'timestamp' => time(),
                            'lock_name' => $lock_name,
                        );
                        ftruncate( $handle, 0 );
                        rewind( $handle );
                        fwrite( $handle, json_encode( $lock_data ) );
                        fflush( $handle );
                        $acquired = true;
                        break;
                    }
                }
            }
        }
    }
    
    if ( ! $acquired ) {
        @flock( $handle, LOCK_UN );
        @fclose( $handle );
        yatco_log( "File Lock: Failed to acquire lock after {$timeout} seconds - another process is running", 'warning' );
        return false;
    }
    
    return array(
        'handle' => $handle,
        'file' => $lock_file,
    );
}

/**
 * Release a file-based lock
 * 
 * @param array $lock Lock data from yatco_acquire_file_lock()
 * @return bool True on success, false on failure
 */
function yatco_release_file_lock( $lock ) {
    if ( ! $lock || ! is_array( $lock ) || ! isset( $lock['handle'] ) ) {
        return false;
    }
    
    $handle = $lock['handle'];
    
    // Release lock
    @flock( $handle, LOCK_UN );
    @fclose( $handle );
    
    // Optionally remove lock file (but keep it for debugging)
    // @unlink( $lock['file'] );
    
    return true;
}

/**
 * Check if a file lock exists and is valid
 * 
 * @param string $lock_name Lock identifier
 * @return array|false Lock info if exists, false otherwise
 */
function yatco_check_file_lock( $lock_name ) {
    $lock_dir = get_temp_dir();
    if ( ! $lock_dir ) {
        $lock_dir = sys_get_temp_dir();
    }
    
    $lock_file = $lock_dir . '/yatco_' . $lock_name . '.lock';
    
    if ( ! file_exists( $lock_file ) ) {
        return false;
    }
    
    $handle = @fopen( $lock_file, 'r' );
    if ( ! $handle ) {
        return false;
    }
    
    // Try non-blocking read
    if ( ! @flock( $handle, LOCK_SH | LOCK_NB ) ) {
        // Lock is held - file exists and is locked
        $content = @fread( $handle, 1024 );
        @flock( $handle, LOCK_UN );
        @fclose( $handle );
        
        if ( $content ) {
            $lock_data = json_decode( $content, true );
            return $lock_data;
        }
    } else {
        @flock( $handle, LOCK_UN );
        @fclose( $handle );
        // File exists but isn't locked - stale
        return false;
    }
    
    return false;
}

/**
 * Clear a stale file lock (force remove)
 * 
 * @param string $lock_name Lock identifier
 * @return bool True on success, false on failure
 */
function yatco_clear_file_lock( $lock_name ) {
    $lock_dir = get_temp_dir();
    if ( ! $lock_dir ) {
        $lock_dir = sys_get_temp_dir();
    }
    
    $lock_file = $lock_dir . '/yatco_' . $lock_name . '.lock';
    
    if ( file_exists( $lock_file ) ) {
        // Try to remove even if locked (force)
        @unlink( $lock_file );
        yatco_log( "File Lock: Cleared lock file: {$lock_file}", 'info' );
        return true;
    }
    
    return false;
}

