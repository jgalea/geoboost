<?php
namespace GeoBoost\Core;

use GeoBoost\Events\EventsTable;

final class Migrations {

	private const OPTION  = 'geoboost_db_version';
	private const CURRENT = '3';

	public static function run(): void {
		$installed = get_option( self::OPTION, '0' );
		if ( version_compare( $installed, self::CURRENT, '>=' ) ) {
			return;
		}
		if ( version_compare( $installed, '2', '<' ) ) {
			EventsTable::create();
		}
		if ( version_compare( $installed, '3', '<' ) ) {
			self::purge_non_post_page_scores();
		}
		update_option( self::OPTION, self::CURRENT );
	}

	/**
	 * Remove stale _geoboost_score* meta from post types other than post/page.
	 * Earlier versions scored every saved post via the generic save_post hook.
	 */
	private static function purge_non_post_page_scores(): void {
		global $wpdb;
		$meta_keys = array( '_geoboost_score', '_geoboost_score_details', '_geoboost_score_history' );
		foreach ( $meta_keys as $key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					"DELETE pm FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s AND p.post_type NOT IN ('post','page')",
					$key
				)
			);
		}
	}
}
