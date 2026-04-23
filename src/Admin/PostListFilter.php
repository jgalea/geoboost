<?php
namespace GeoBoost\Admin;

final class PostListFilter {

	private const QUERY_ARG = 'geoboost_filter';

	private const SIGNALS = array(
		'missing_summary'     => 'has_summary',
		'missing_key_facts'   => 'has_key_facts',
		'missing_faq'         => 'has_faq_signal',
		'heading_hierarchy'   => 'heading_hierarchy_ok',
		'word_count'          => 'word_count_ok',
		'missing_author_eeat' => 'has_author_eeat',
		'missing_featured'    => 'has_featured',
	);

	private const LABELS = array(
		'missing_summary'     => 'Missing Summary block',
		'missing_key_facts'   => 'Missing Key Facts',
		'missing_faq'         => 'Missing FAQ signals',
		'heading_hierarchy'   => 'Heading hierarchy issues',
		'word_count'          => 'Outside 300–3000 word sweet spot',
		'missing_author_eeat' => 'Weak author E-E-A-T signals',
		'missing_featured'    => 'No featured image',
	);

	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
	}

	public function filter_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$filter = $this->current_filter();
		if ( null === $filter ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}
		$ids = $this->matching_post_ids( $filter );
		// An empty array would match everything; use a sentinel to force zero results.
		$query->set( 'post__in', empty( $ids ) ? array( 0 ) : $ids );
	}

	public function show_notice(): void {
		$filter = $this->current_filter();
		if ( null === $filter ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}
		$clear = remove_query_arg( self::QUERY_ARG );
		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: filter label */
					__( 'GEOBoost filter active: %s.', 'geoboost' ),
					self::LABELS[ $filter ]
				)
			),
			esc_url( (string) $clear ),
			esc_html__( 'Clear filter', 'geoboost' )
		);
	}

	private function current_filter(): ?string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET[ self::QUERY_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_ARG ] ) ) : '';
		if ( '' === $raw || ! isset( self::SIGNALS[ $raw ] ) ) {
			return null;
		}
		return $raw;
	}

	/**
	 * @return array<int,int>
	 */
	private function matching_post_ids( string $filter ): array {
		global $wpdb;
		$signal = self::SIGNALS[ $filter ];
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT post_id, meta_value AS details FROM {$wpdb->postmeta} WHERE meta_key = '_geoboost_score_details'"
		);
		$ids    = array();
		foreach ( (array) $rows as $row ) {
			$d = maybe_unserialize( $row->details );
			if ( ! is_array( $d ) ) {
				continue;
			}
			if ( empty( $d[ $signal ] ) ) {
				$ids[] = (int) $row->post_id;
			}
		}
		return $ids;
	}
}
