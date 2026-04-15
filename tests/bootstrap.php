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
