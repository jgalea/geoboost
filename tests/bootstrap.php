<?php
/**
 * PHPUnit bootstrap for unit tests (no WP).
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Minimal WP function stubs for unit tests that reference WP globals.
// Integration tests that need the full WP stack use a separate harness.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/fake-wp/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
    define( 'YEAR_IN_SECONDS', 31536000 );
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $code;
        public $message;
        public function __construct( string $code = '', string $message = '' ) {
            $this->code    = $code;
            $this->message = $message;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool {
        return $thing instanceof \WP_Error;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, int $flags = 0, int $depth = 512 ) {
        return json_encode( $data, $flags, $depth );
    }
}
