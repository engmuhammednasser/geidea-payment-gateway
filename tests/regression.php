<?php
/**
 * Offline regression checks for browser returns and verified payment callbacks.
 * Requires PHP 7.4+; no WordPress install, Composer packages, or network access.
 */

if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 403 );
    exit( 'Run this test from the command line.' );
}

error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

$source_root = dirname( __DIR__ );
foreach ( array_slice( $argv, 1 ) as $argument ) {
    if ( strpos( $argument, '--source-root=' ) !== 0 ) {
        fwrite( STDERR, "Usage: php tests/regression.php [--source-root=/path/to/plugin]\n" );
        exit( 2 );
    }
    $source_root = substr( $argument, strlen( '--source-root=' ) );
}
$source_root = realpath( $source_root );
if ( false === $source_root || ! is_file( $source_root . '/includes/class-gpg-rest.php' ) ) {
    fwrite( STDERR, "The source root must contain the Geidea plugin includes directory.\n" );
    exit( 2 );
}

define( 'ABSPATH', $source_root . '/' );
define( 'GPG_PLUGIN_DIR', $source_root . '/' );
define( 'GPG_PLUGIN_URL', 'https://example.invalid/plugin/' );
define( 'GPG_VERSION', 'test' );

$test_upload_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gpg-regression-' . bin2hex( random_bytes( 8 ) );
mkdir( $test_upload_root, 0700 );
register_shutdown_function( function () use ( $test_upload_root ) {
    // Remove only files this harness permits the real logger to create.
    foreach ( array( '.htaccess', 'index.php', 'geidea-payment-synthetic-test-log.log' ) as $file ) {
        $path = $test_upload_root . '/geidea-logs/' . $file;
        if ( is_file( $path ) ) {
            unlink( $path );
        }
    }
    if ( is_dir( $test_upload_root . '/geidea-logs' ) ) {
        rmdir( $test_upload_root . '/geidea-logs' );
    }
    if ( is_dir( $test_upload_root ) ) {
        rmdir( $test_upload_root );
    }
} );

class GPG_Test_Redirect extends RuntimeException {
    public $location;
    public function __construct( $location ) {
        parent::__construct( 'Captured redirect' );
        $this->location = $location;
    }
}

class WP_REST_Request {
    private $payload;
    public function __construct( array $payload ) {
        $this->payload = $payload;
    }
    public function get_json_params() {
        return $this->payload;
    }
}

class WP_REST_Response {
    private $data;
    private $status;
    public function __construct( $data = null, $status = 200 ) {
        $this->data = $data;
        $this->status = $status;
    }
    public function get_data() {
        return $this->data;
    }
    public function get_status() {
        return $this->status;
    }
}

function reset_test_state( $mode = 'live', $debug = false ) {
    $GLOBALS['gpg_test'] = array(
        'actions' => array(),
        'filters' => array(),
        'shortcodes' => array(),
        'routes' => array(),
        'events' => array(),
        'mutations' => array(),
        'contexts' => array( 42 => array( 'amount' => 150.25 ) ),
        'options' => array(
            'gpg_log_hash' => 'synthetic-test-log',
            'GPG_settings' => array(
                'GPG_enabled' => 1,
                'GPG_mode' => $mode,
                'GPG_currency' => 'SAR',
                'GPG_debug_log' => $debug,
                'GPG_live_public_key' => 'synthetic-live-public-key',
                'GPG_live_api_password' => 'synthetic-live-password',
                'GPG_sandbox_public_key' => 'synthetic-test-public-key',
                'GPG_sandbox_api_password' => 'synthetic-test-password',
                'GPG_success_url' => 'https://example.invalid/success/',
                'GPG_failed_url' => 'https://example.invalid/failed/',
                'GPG_cancel_url' => 'https://example.invalid/cancel/',
            ),
        ),
    );
    $_GET = array();
}

// WordPress infrastructure only: production gateway classes are loaded below.
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['gpg_test']['actions'][$tag][] = $callback;
}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['gpg_test']['filters'][$tag][] = $callback;
}
function add_shortcode( $tag, $callback ) {
    $GLOBALS['gpg_test']['shortcodes'][$tag] = $callback;
}
function do_action( $tag, ...$args ) {
    $GLOBALS['gpg_test']['events'][] = array( 'tag' => $tag, 'args' => $args );
    foreach ( $GLOBALS['gpg_test']['actions'][$tag] ?? array() as $callback ) {
        call_user_func_array( $callback, $args );
    }
}
function apply_filters( $tag, $value, ...$args ) {
    if ( 'GPG_booking_context' === $tag ) {
        return $GLOBALS['gpg_test']['contexts'][$args[0]] ?? $value;
    }
    foreach ( $GLOBALS['gpg_test']['filters'][$tag] ?? array() as $callback ) {
        $value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
    }
    return $value;
}
function register_rest_route( $namespace, $route, $options ) {
    $GLOBALS['gpg_test']['routes'][$namespace . $route] = $options;
}
function get_option( $key, $default = false ) {
    return $GLOBALS['gpg_test']['options'][$key] ?? $default;
}
function update_option( $key, $value ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'update_option', $key, $value );
    $GLOBALS['gpg_test']['options'][$key] = $value;
    return true;
}
function wp_delete_post( ...$args ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'wp_delete_post', $args );
}
function wp_trash_post( ...$args ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'wp_trash_post', $args );
}
function update_post_meta( ...$args ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'update_post_meta', $args );
}
function add_post_meta( ...$args ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'add_post_meta', $args );
}
function delete_post_meta( ...$args ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'delete_post_meta', $args );
}
function wp_update_post( ...$args ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'wp_update_post', $args );
}
function wp_insert_post( ...$args ) {
    $GLOBALS['gpg_test']['mutations'][] = array( 'wp_insert_post', $args );
}
function home_url( $path = '' ) {
    return 'https://example.invalid' . $path;
}
function wp_redirect( $location, $status = 302, $redirect_by = 'WordPress' ) {
    // Interrupt immediately before production exit, without modifying its code.
    throw new GPG_Test_Redirect( $location );
}
function sanitize_text_field( $value ) {
    if ( ! is_scalar( $value ) ) {
        return '';
    }
    return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) );
}
function wp_unslash( $value ) {
    return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value );
}
function absint( $value ) {
    return abs( (int) $value );
}
function wp_json_encode( $value ) {
    return json_encode( $value );
}
function current_time( $format ) {
    return '2026-01-01 00:00:00';
}
function wp_upload_dir() {
    return array( 'basedir' => $GLOBALS['test_upload_root'] );
}
function wp_mkdir_p( $path ) {
    return mkdir( $path, 0700, true );
}
function wp_remote_post( ...$args ) {
    throw new RuntimeException( 'Network access is forbidden in this regression harness.' );
}

foreach ( array( 'settings', 'signature', 'api-client', 'logger', 'booking', 'rest' ) as $component ) {
    require_once $source_root . '/includes/class-gpg-' . $component . '.php';
}

function expect_same( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        throw new RuntimeException( $message . '; expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
    }
}
function payment_events() {
    return array_values( array_filter( $GLOBALS['gpg_test']['events'], function ( $event ) {
        return 0 === strpos( $event['tag'], 'GPG_payment_' );
    } ) );
}
function assert_read_only_return() {
    expect_same( array(), $GLOBALS['gpg_test']['mutations'], 'Browser return wrote or deleted data' );
    expect_same( array(), payment_events(), 'Browser return emitted a payment-state event' );
}
function signed_payload( $success = true ) {
    $client = new GPG_API_Client();
    $payload = array(
        'merchantReferenceId' => ( 'live' === $client->get_mode() ? 'LIVE-KSA-CAR-' : 'TEST-KSA-CAR-' ) . '42-1700000000',
        'responseCode' => $success ? '000' : '999',
        'detailedResponseCode' => $success ? '000' : '123',
        'amount' => 150.25,
        'currency' => 'SAR',
        'timestamp' => '2026-01-01 00:00:00',
        'source' => 'synthetic-source-never-log-this-value',
    );
    $payload['signature'] = GPG_Signature::generate(
        $client->get_public_key(), $payload['amount'], $payload['currency'],
        $payload['merchantReferenceId'], $client->get_api_password(), $payload['timestamp']
    );
    return $payload;
}
function dispatch_callback( array $payload ) {
    new GPG_REST();
    do_action( 'rest_api_init' );
    $route = $GLOBALS['gpg_test']['routes']['geidea-booking/v1/callback'];
    expect_same( 'POST', $route['methods'], 'Callback route method changed' );
    return call_user_func( $route['callback'], new WP_REST_Request( $payload ) );
}

$tests = array();
$return_cases = array(
    'success' => array( array( 'responseCode' => '000', 'booking_id' => '42' ), 'success' ),
    'cancel booking' => array( array( 'responseCode' => '010', 'booking_id' => '42' ), 'cancel' ),
    'failure unrelated post' => array( array( 'responseCode' => '999', 'booking_id' => '9999' ), 'failed' ),
    'unknown code' => array( array( 'responseCode' => 'unexpected', 'booking_id' => '42' ), 'failed' ),
    'empty code' => array( array( 'responseCode' => '', 'booking_id' => '42' ), 'failed' ),
    'missing booking ID' => array( array( 'responseCode' => '010' ), 'cancel' ),
    'malformed booking ID' => array( array( 'responseCode' => '999', 'booking_id' => array( '42' ) ), 'failed' ),
    'string sanitation' => array( array( 'responseCode' => ' <b>000</b> ' ), 'success' ),
    'WordPress slashed non-success value' => array( array( 'responseCode' => addslashes( "'000'" ) ), 'failed' ),
    'missing code' => array( array( 'booking_id' => '42' ), null ),
    'array code' => array( array( 'responseCode' => array( '010' ), 'booking_id' => '42' ), null ),
    'integer code' => array( array( 'responseCode' => 0, 'booking_id' => '42' ), null ),
    'boolean code' => array( array( 'responseCode' => true, 'booking_id' => '42' ), null ),
    'object code' => array( array( 'responseCode' => new stdClass(), 'booking_id' => '42' ), null ),
    'null code' => array( array( 'responseCode' => null, 'booking_id' => '42' ), null ),
);
foreach ( $return_cases as $name => $case ) {
    $tests['return: ' . $name] = function () use ( $case ) {
        reset_test_state();
        new GPG_Booking();
        $_GET = $case[0];
        $destination = null;
        try {
            do_action( 'template_redirect' );
        } catch ( GPG_Test_Redirect $redirect ) {
            $destination = $redirect->location;
        }
        assert_read_only_return();
        expect_same( null === $case[1] ? null : 'https://example.invalid/' . $case[1] . '/', $destination, 'Unexpected return destination' );
    };
}
$tests['return: disabled gateway still cannot delete'] = function () {
    reset_test_state();
    $GLOBALS['gpg_test']['options']['GPG_settings']['GPG_enabled'] = 0;
    new GPG_Booking();
    $_GET = array( 'responseCode' => '010', 'booking_id' => '42' );
    try {
        do_action( 'template_redirect' );
    } catch ( GPG_Test_Redirect $redirect ) {
        expect_same( 'https://example.invalid/cancel/', $redirect->location, 'Cancel destination changed' );
    }
    assert_read_only_return();
};
$tests['return: blank destination is harmless'] = function () {
    reset_test_state();
    $GLOBALS['gpg_test']['options']['GPG_settings']['GPG_failed_url'] = '';
    new GPG_Booking();
    $_GET = array( 'responseCode' => '999', 'booking_id' => '42' );
    do_action( 'template_redirect' );
    assert_read_only_return();
};

foreach ( array( 'live', 'sandbox' ) as $mode ) {
    foreach ( array( false, true ) as $debug ) {
        foreach ( array( false, true ) as $success ) {
            $name = 'callback: ' . $mode . ' ' . ( $success ? 'success' : 'failure' ) . ' logging ' . ( $debug ? 'on' : 'off' );
            $tests[$name] = function () use ( $mode, $debug, $success, $test_upload_root ) {
                reset_test_state( $mode, $debug );
                $log_file = $test_upload_root . '/geidea-logs/geidea-payment-synthetic-test-log.log';
                if ( is_file( $log_file ) ) {
                    unlink( $log_file );
                }
                $response = dispatch_callback( signed_payload( $success ) );
                expect_same( 200, $response->get_status(), 'Validated callback failed' );
                expect_same( 'OK', $response->get_data(), 'Callback acknowledgement changed' );
                $events = payment_events();
                expect_same( 1, count( $events ), 'Expected exactly one payment-state event' );
                expect_same( $success ? 'GPG_payment_completed' : 'GPG_payment_failed', $events[0]['tag'], 'Wrong payment-state event' );
                expect_same( 42, $events[0]['args'][0], 'Wrong booking ID' );
                expect_same( '***masked***', $events[0]['args'][1]['source'], 'Sensitive source field was not masked' );
                $expected_status = $success ? ( 'live' === $mode ? 'paid' : 'paid_test' ) : 'payment_failed';
                expect_same( $expected_status, $events[0]['args'][2], 'Wrong booking status' );
                expect_same( array(), $GLOBALS['gpg_test']['mutations'], 'Callback directly mutated integration-owned booking data' );
                expect_same( $debug, is_file( $log_file ), 'Logging setting was not respected' );
                if ( $debug ) {
                    $log = file_get_contents( $log_file );
                    expect_same( true, false !== strpos( $log, '(INFO): Payment ' ), 'Callback did not write its informational log' );
                    expect_same( false, strpos( $log, 'synthetic-source-never-log-this-value' ), 'Raw source leaked into log' );
                }
            };
        }
    }
}

foreach ( array( 'signature', 'currency', 'amount', 'mode' ) as $invalid ) {
    $tests['callback: reject invalid ' . $invalid] = function () use ( $invalid ) {
        reset_test_state();
        $payload = signed_payload();
        $expected_message = '';
        if ( 'signature' === $invalid ) {
            $payload['signature'] = 'invalid-synthetic-signature';
            $expected_message = 'Invalid signature';
        } elseif ( 'currency' === $invalid ) {
            $payload['currency'] = 'USD';
            $expected_message = 'Invalid currency';
        } elseif ( 'amount' === $invalid ) {
            $GLOBALS['gpg_test']['contexts'][42]['amount'] = 200;
            $expected_message = 'Amount mismatch';
        } else {
            $GLOBALS['gpg_test']['options']['GPG_settings']['GPG_mode'] = 'sandbox';
            $expected_message = 'Mode mismatch';
        }
        $response = dispatch_callback( $payload );
        expect_same( 400, $response->get_status(), 'Invalid callback was accepted' );
        expect_same( $expected_message, $response->get_data(), 'Unexpected validation failure' );
        expect_same( array(), payment_events(), 'Invalid callback emitted a payment-state event' );
        expect_same( array(), $GLOBALS['gpg_test']['mutations'], 'Invalid callback mutated data' );
    };
}

$passed = 0;
$failed = 0;
foreach ( $tests as $name => $test ) {
    try {
        $test();
        ++$passed;
        echo 'PASS ' . $name . "\n";
    } catch ( Throwable $error ) {
        ++$failed;
        echo 'FAIL ' . $name . ': ' . $error->getMessage() . "\n";
    }
}
echo "\n" . $passed . ' passed, ' . $failed . " failed\n";
exit( $failed > 0 ? 1 : 0 );
