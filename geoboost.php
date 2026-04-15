<?php
/**
 * Plugin Name: GEOBoost
 * Description: Generative Engine Optimization for WordPress. Make your site AI-citable.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Jean Galea
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: geoboost
 */

defined( 'ABSPATH' ) || exit;

define( 'GEOBOOST_VERSION', '0.1.0' );
define( 'GEOBOOST_FILE', __FILE__ );
define( 'GEOBOOST_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEOBOOST_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', static function (): void {
    \GeoBoost\Core\Plugin::instance()->boot();
} );

register_activation_hook( __FILE__, [ \GeoBoost\Core\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \GeoBoost\Core\Deactivator::class, 'deactivate' ] );
