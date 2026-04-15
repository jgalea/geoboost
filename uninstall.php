<?php
/**
 * Runs when the plugin is deleted from the WP admin.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'geoboost\\_%'"
);

delete_transient( 'geoboost_llms_txt' );

require_once __DIR__ . '/vendor/autoload.php';
\GeoBoost\Events\EventsTable::drop();
delete_option( 'geoboost_db_version' );
