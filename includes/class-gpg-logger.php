<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_Logger {

    public static function log( $message, $level = 'info' ) {
        if ( ! GPG_Settings::get_setting( 'GPG_debug_log' ) ) {
            return;
        }

        if ( is_array( $message ) || is_object( $message ) ) {
            $message = wp_json_encode( $message );
        }

        $log_entry = sprintf( "[%s] Geidea KSA Gateway (%s): %s", current_time( 'mysql' ), strtoupper( $level ), $message );
        
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/geidea-logs';
        
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            
            // Protect directory from direct access
            file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
            file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
        }
        
        $hash = get_option('gpg_log_hash');
        if ( ! $hash ) {
            $hash = wp_generate_password( 20, false, false );
            update_option( 'gpg_log_hash', $hash );
        }
        $log_file = $log_dir . '/geidea-payment-' . $hash . '.log';
        
        // Log Rotation: if greater than 5MB
        if ( file_exists( $log_file ) && filesize( $log_file ) > 5 * 1024 * 1024 ) {
            rename( $log_file, $log_dir . '/geidea-payment-' . $hash . '-old.log' );
        }
        
        // Write to our custom log file
        file_put_contents( $log_file, $log_entry . PHP_EOL, FILE_APPEND );
    }

    public static function debug( $message ) {
        self::log( $message, 'debug' );
    }

    public static function error( $message ) {
        self::log( $message, 'error' );
    }
}

