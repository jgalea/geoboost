<?php
namespace GeoBoost\Events;

final class Pruner {

	public const HOOK = 'geoboost_prune_events';

	public function register(): void {
		add_action( self::HOOK, array( $this, 'prune' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		$next = wp_next_scheduled( self::HOOK );
		if ( $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
	}

	public function prune(): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'DELETE FROM ' . EventsTable::name() . ' WHERE created_at < %s',
			$cutoff
		) );
	}
}
