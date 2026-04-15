<?php
namespace GeoBoost\Events;

final class EventsTable {

	public static function name(): string {
		global $wpdb;
		return $wpdb->prefix . 'geoboost_events';
	}

	public static function create(): void {
		global $wpdb;
		$table   = self::name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(32) NOT NULL,
			bot_or_source VARCHAR(64) NOT NULL DEFAULT '',
			path VARCHAR(255) NOT NULL DEFAULT '',
			status_code SMALLINT NOT NULL DEFAULT 0,
			user_agent TEXT NULL,
			referer TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type_created_at (event_type, created_at),
			KEY bot_or_source (bot_or_source)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop(): void {
		global $wpdb;
		$table = self::name();
		// Table name is built from $wpdb->prefix plus a fixed literal; safe to interpolate.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}
