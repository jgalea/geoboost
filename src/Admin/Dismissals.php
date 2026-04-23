<?php
namespace GeoBoost\Admin;

final class Dismissals {

	public const META_KEY = '_geoboost_fix_dismissed';

	public function dismissed_for( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $raw ) ) );
	}

	public function is_dismissed( int $post_id, string $type ): bool {
		return in_array( $type, $this->dismissed_for( $post_id ), true );
	}

	public function dismiss( int $post_id, string $type ): void {
		$current = $this->dismissed_for( $post_id );
		if ( in_array( $type, $current, true ) ) {
			return;
		}
		$current[] = $type;
		update_post_meta( $post_id, self::META_KEY, $current );
	}

	public function restore( int $post_id, string $type ): void {
		$current = $this->dismissed_for( $post_id );
		$new     = array_values( array_diff( $current, array( $type ) ) );
		if ( empty( $new ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}
		update_post_meta( $post_id, self::META_KEY, $new );
	}

	/**
	 * Return the set of post IDs that have dismissed the given type.
	 *
	 * @return array<int,int>
	 */
	public function dismissed_ids_for_type( string $type ): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_KEY
			)
		);
		$ids  = array();
		foreach ( (array) $rows as $row ) {
			$list = maybe_unserialize( $row->meta_value );
			if ( is_array( $list ) && in_array( $type, $list, true ) ) {
				$ids[] = (int) $row->post_id;
			}
		}
		return $ids;
	}
}
