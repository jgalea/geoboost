<?php
namespace GeoBoost\Events;

final class EventLogger {

	/** @var callable */
	private $writer;

	public function __construct( ?callable $writer = null ) {
		$this->writer = $writer ?? array( $this, 'db_writer' );
	}

	public function log( string $event_type, array $fields ): void {
		$row = array(
			'event_type'    => $event_type,
			'bot_or_source' => substr( (string) ( $fields['bot_or_source'] ?? '' ), 0, 64 ),
			'path'          => substr( (string) ( $fields['path'] ?? '' ), 0, 255 ),
			'status_code'   => (int) ( $fields['status_code'] ?? 0 ),
			'user_agent'    => substr( (string) ( $fields['user_agent'] ?? '' ), 0, 1000 ),
			'referer'       => substr( (string) ( $fields['referer'] ?? '' ), 0, 1000 ),
			'created_at'    => gmdate( 'Y-m-d H:i:s' ),
		);
		( $this->writer )( $row );
	}

	private function db_writer( array $row ): void {
		global $wpdb;
		$wpdb->insert( EventsTable::name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
