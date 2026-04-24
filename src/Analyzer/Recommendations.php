<?php
namespace GeoBoost\Analyzer;

final class Recommendations {

	/** @return array<int,array{signal:string,text:string}> */
	public function for_signals( array $signals ): array {
		$recs = array();
		if ( empty( $signals['has_summary_block'] ) ) {
			$recs[] = array(
				'signal' => 'has_summary_block',
				'text'   => __( 'Add a GEOBoost Summary (TL;DR) block near the top.', 'geoboost' ),
			);
		}
		if ( empty( $signals['has_excerpt'] ) ) {
			$recs[] = array(
				'signal' => 'has_excerpt',
				'text'   => __( 'Add a post excerpt — feeds Article schema description and llms.txt.', 'geoboost' ),
			);
		}
		if ( empty( $signals['has_key_facts'] ) ) {
			$recs[] = array(
				'signal' => 'has_key_facts',
				'text'   => __( 'Add a GEOBoost Key Facts block with 3–6 structured facts.', 'geoboost' ),
			);
		}
		if ( empty( $signals['has_faq_signal'] ) ) {
			$recs[] = array(
				'signal' => 'has_faq_signal',
				'text'   => __( 'Consider adding a FAQ block with 3–5 common questions.', 'geoboost' ),
			);
		}
		if ( empty( $signals['has_featured'] ) ) {
			$recs[] = array(
				'signal' => 'has_featured',
				'text'   => __( 'Add a featured image with descriptive alt text.', 'geoboost' ),
			);
		}
		if ( empty( $signals['has_outbound_link'] ) ) {
			$recs[] = array(
				'signal' => 'has_outbound_link',
				'text'   => __( 'Cite at least one external authoritative source.', 'geoboost' ),
			);
		}
		if ( empty( $signals['has_internal_link'] ) ) {
			$recs[] = array(
				'signal' => 'has_internal_link',
				'text'   => __( 'Link to at least one related post on your own site.', 'geoboost' ),
			);
		}
		return $recs;
	}
}
