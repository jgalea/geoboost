<?php
namespace GeoBoost\Admin\Dashboard;

final class RecommendationsWidget {

	public function render(): string {
		global $wpdb;
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT post_id, meta_value AS details FROM {$wpdb->postmeta} WHERE meta_key = '_geoboost_score_details'"
		);
		$counts = array(
			'missing_summary'     => 0,
			'missing_key_facts'   => 0,
			'missing_faq'         => 0,
			'heading_hierarchy'   => 0,
			'word_count'          => 0,
			'missing_author_eeat' => 0,
			'missing_featured'    => 0,
		);
		foreach ( (array) $rows as $row ) {
			$d = maybe_unserialize( $row->details );
			if ( ! is_array( $d ) ) {
				continue;
			}
			if ( empty( $d['has_summary'] ) ) {
				++$counts['missing_summary'];
			}
			if ( empty( $d['has_key_facts'] ) ) {
				++$counts['missing_key_facts'];
			}
			if ( empty( $d['has_faq_signal'] ) ) {
				++$counts['missing_faq'];
			}
			if ( empty( $d['heading_hierarchy_ok'] ) ) {
				++$counts['heading_hierarchy'];
			}
			if ( empty( $d['word_count_ok'] ) ) {
				++$counts['word_count'];
			}
			if ( empty( $d['has_author_eeat'] ) ) {
				++$counts['missing_author_eeat'];
			}
			if ( empty( $d['has_featured'] ) ) {
				++$counts['missing_featured'];
			}
		}
		$labels = array(
			'missing_summary'     => __( 'posts missing a Summary block', 'geoboost' ),
			'missing_key_facts'   => __( 'posts missing Key Facts', 'geoboost' ),
			'missing_faq'         => __( 'posts missing FAQ signals', 'geoboost' ),
			'heading_hierarchy'   => __( 'posts with heading hierarchy issues', 'geoboost' ),
			'word_count'          => __( 'posts outside 300–3000 word sweet spot', 'geoboost' ),
			'missing_author_eeat' => __( 'posts with weak author E-E-A-T signals', 'geoboost' ),
			'missing_featured'    => __( 'posts without a featured image', 'geoboost' ),
		);
		$out    = '<h2>' . esc_html__( 'Recommendations', 'geoboost' ) . '</h2><ul>';
		foreach ( $counts as $k => $n ) {
			if ( $n > 0 ) {
				$out .= sprintf( '<li><strong>%d</strong> %s</li>', $n, esc_html( $labels[ $k ] ) );
			}
		}
		$out .= '</ul>';
		return $out;
	}
}
