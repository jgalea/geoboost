<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

function geoboost_uninstall_site(): void {
	global $wpdb;

	// Fixed keys first, so transients held in an object cache go too.
	foreach ( array( 'geoboost_llms_txt', 'geoboost_llms_override', 'geoboost_dashboard_snapshot', 'geoboost_scan_token' ) as $transient ) {
		delete_transient( $transient );
	}

	$names = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT option_name FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( 'geoboost_' ) . '%',
			$wpdb->esc_like( '_transient_geoboost_' ) . '%'
		)
	);
	foreach ( $names as $name ) {
		if ( 0 === strpos( $name, '_transient_' ) ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		} else {
			delete_option( $name );
		}
	}
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_timeout_geoboost_' ) . '%'
		)
	);

	$meta_keys = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT DISTINCT meta_key FROM %i WHERE meta_key LIKE %s',
			$wpdb->postmeta,
			$wpdb->esc_like( '_geoboost_' ) . '%'
		)
	);
	foreach ( $meta_keys as $key ) {
		delete_post_meta_by_key( $key );
	}

	wp_clear_scheduled_hook( \GeoBoost\Events\Pruner::HOOK );
	\GeoBoost\Events\EventsTable::drop();
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $geoboost_site_id ) {
		switch_to_blog( (int) $geoboost_site_id );
		geoboost_uninstall_site();
		restore_current_blog();
	}
} else {
	geoboost_uninstall_site();
}

// User meta is shared by every site in a network, so it's cleared once.
global $wpdb;
$geoboost_user_meta_keys = $wpdb->get_col(
	$wpdb->prepare(
		'SELECT DISTINCT meta_key FROM %i WHERE meta_key LIKE %s',
		$wpdb->usermeta,
		$wpdb->esc_like( 'geoboost_' ) . '%'
	)
);
foreach ( $geoboost_user_meta_keys as $geoboost_key ) {
	delete_metadata( 'user', 0, $geoboost_key, '', true );
}
