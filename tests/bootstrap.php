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
        public function get_error_message(): string {
            return $this->message;
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

if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = 'default' ): string {
        return $text;
    }
}

if ( ! function_exists( '_n' ) ) {
    function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
        return 1 === $number ? $single : $plural;
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return esc_html( $text );
    }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( string $text, string $domain = 'default' ): string {
        return esc_attr( $text );
    }
}

if ( ! function_exists( 'number_format_i18n' ) ) {
    function number_format_i18n( $number, int $decimals = 0 ): string {
        return number_format( (float) $number, $decimals );
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( string $url, int $component = -1 ) {
        return parse_url( $url, $component );
    }
}

if ( ! function_exists( 'get_query_var' ) ) {
    function get_query_var( string $name, $default = '' ) {
        return $GLOBALS['geoboost_test_query_vars'][ $name ] ?? $default;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( string $hook, $value, ...$args ) {
        return $value;
    }
}

// A tiny fake web for the HTTP API: $GLOBALS['geoboost_test_http']['routes'][ $url ]
// holds a response, and every URL actually requested is appended to ['log'].
// Like core, it follows redirects when 'redirection' > 0, and the safe variant
// rejects private and loopback IPs.
if ( ! function_exists( 'geoboost_test_http_request' ) ) {
    function geoboost_test_http_request( string $url, array $args, bool $safe ) {
        $hops = (int) ( $args['redirection'] ?? 5 );
        while ( true ) {
            if ( $safe && preg_match( '#^https?://(127\.|10\.|0\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)#', $url ) ) {
                return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
            }
            $GLOBALS['geoboost_test_http']['log'][] = $url;
            $route    = $GLOBALS['geoboost_test_http']['routes'][ $url ] ?? array( 'status' => 404 );
            $status   = (int) ( $route['status'] ?? 200 );
            $headers  = array_change_key_case( (array) ( $route['headers'] ?? array() ) );
            $location = $headers['location'] ?? '';
            if ( $status >= 300 && $status < 400 && '' !== $location && $hops-- > 0 ) {
                $url = '/' === $location[0] ? preg_replace( '#^(https?://[^/]+).*$#', '$1', $url ) . $location : $location;
                continue;
            }
            return array(
                'headers'  => $headers,
                'body'     => (string) ( $route['body'] ?? '' ),
                'response' => array( 'code' => $status ),
            );
        }
    }

    function wp_remote_get( string $url, array $args = array() ) {
        return geoboost_test_http_request( $url, $args, ! empty( $args['reject_unsafe_urls'] ) );
    }

    function wp_safe_remote_get( string $url, array $args = array() ) {
        return geoboost_test_http_request( $url, $args, true );
    }

    function wp_remote_retrieve_headers( $response ) {
        return $response['headers'] ?? array();
    }

    function wp_remote_retrieve_response_code( $response ) {
        return $response['response']['code'] ?? '';
    }

    function wp_remote_retrieve_body( $response ) {
        return $response['body'] ?? '';
    }
}
