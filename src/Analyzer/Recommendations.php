<?php
namespace GeoBoost\Analyzer;

final class Recommendations {

	/** @return array<int,string> */
	public function for_signals( array $signals ): array {
		$recs = array();
		if ( empty( $signals['has_summary'] ) ) {
			$recs[] = __( 'Add a GEOBoost Summary (TL;DR) block near the top.', 'geoboost' );
		}
		if ( empty( $signals['has_key_facts'] ) ) {
			$recs[] = __( 'Add a GEOBoost Key Facts block with 3–6 structured facts.', 'geoboost' );
		}
		if ( empty( $signals['has_faq_signal'] ) ) {
			$recs[] = __( 'Consider adding a FAQ block with 3–5 common questions.', 'geoboost' );
		}
		if ( empty( $signals['heading_hierarchy_ok'] ) ) {
			$recs[] = __( 'Fix heading hierarchy: avoid skipping H levels (e.g. H2 → H4).', 'geoboost' );
		}
		if ( empty( $signals['word_count_ok'] ) ) {
			$recs[] = __( 'Aim for 300–3000 words for best LLM citation odds.', 'geoboost' );
		}
		if ( empty( $signals['has_author_eeat'] ) ) {
			$recs[] = __( 'Fill in the author\'s credentials and expertise areas in their user profile.', 'geoboost' );
		}
		if ( empty( $signals['has_article_schema'] ) ) {
			$recs[] = __( 'Article schema is missing. GEOBoost can output it — check Schema settings.', 'geoboost' );
		}
		if ( empty( $signals['has_featured'] ) ) {
			$recs[] = __( 'Add a featured image with descriptive alt text.', 'geoboost' );
		}
		if ( empty( $signals['has_outbound_link'] ) ) {
			$recs[] = __( 'Cite at least one external authoritative source.', 'geoboost' );
		}
		if ( empty( $signals['has_internal_link'] ) ) {
			$recs[] = __( 'Link to at least one related post on your own site.', 'geoboost' );
		}
		return $recs;
	}
}
