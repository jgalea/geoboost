<?php
namespace GeoBoost\Admin\MetaBoxes;

use GeoBoost\Analyzer\ContentAnalyzer;
use GeoBoost\Analyzer\Recommendations;
use GeoBoost\Analyzer\Scorer;

final class ScoreBox {

	private const META_SCORE   = '_geoboost_score';
	private const META_DETAILS = '_geoboost_score_details';
	private const META_HISTORY = '_geoboost_score_history';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'recompute' ), 20, 2 );
	}

	public function add_box(): void {
		foreach ( array( 'post', 'page' ) as $pt ) {
			add_meta_box(
				'geoboost_score',
				__( 'GEOBoost Score', 'geoboost' ),
				array( $this, 'render' ),
				$pt,
				'side',
				'high'
			);
		}
	}

	public function render( \WP_Post $post ): void {
		$score   = (int) get_post_meta( $post->ID, self::META_SCORE, true );
		$details = (array) get_post_meta( $post->ID, self::META_DETAILS, true );
		$grade   = $this->grade( $score );
		printf(
			'<p style="font-size:2em;margin:0.25em 0;"><strong>%d</strong> <span style="color:#50575e">/100 (%s)</span></p>',
			$score,
			esc_html( $grade )
		);
		$recs = ( new Recommendations() )->for_signals( $details );
		if ( ! empty( $recs ) ) {
			echo '<p>' . esc_html__( 'Suggestions:', 'geoboost' ) . '</p><ul style="margin-left:1em;list-style:disc">';
			foreach ( $recs as $r ) {
				echo '<li>' . esc_html( $r ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p class="description">' . esc_html__( 'Score updates on save.', 'geoboost' ) . '</p>';
	}

	public function recompute( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'draft' !== $post->post_status && 'private' !== $post->post_status ) {
			return;
		}
		$author_id     = (int) $post->post_author;
		$author_fields = array(
			'credentials' => (string) get_user_meta( $author_id, 'geoboost_credentials', true ),
			'knowsAbout'  => (array) get_user_meta( $author_id, 'geoboost_expertise', true ),
		);
		$signals       = ( new ContentAnalyzer() )->analyze(
			array(
				'title'              => $post->post_title,
				'content_html'       => (string) apply_filters( 'the_content', $post->post_content ),
				'excerpt'            => (string) $post->post_excerpt,
				'author_fields'      => $author_fields,
				'has_featured'       => has_post_thumbnail( $post_id ),
				'has_article_schema' => true, // we emit it via Schema/Output unless SEO plugin does
				'has_custom_jsonld'  => '' !== trim( (string) get_post_meta( $post_id, '_geoboost_custom_jsonld', true ) ),
				'site_host'          => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);
		$signals['recent'] = ( time() - (int) get_post_time( 'U', true, $post ) ) < ( 2 * YEAR_IN_SECONDS );

		$score   = ( new Scorer() )->score( $signals );
		$history = (array) get_post_meta( $post_id, self::META_HISTORY, true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$history = ( new Scorer() )->append_history( $history, time(), $score );

		update_post_meta( $post_id, self::META_SCORE, $score );
		update_post_meta( $post_id, self::META_DETAILS, $signals );
		update_post_meta( $post_id, self::META_HISTORY, $history );
	}

	private function grade( int $score ): string {
		if ( $score >= 85 ) {
			return 'A';
		}
		if ( $score >= 70 ) {
			return 'B';
		}
		if ( $score >= 55 ) {
			return 'C';
		}
		if ( $score >= 40 ) {
			return 'D';
		}
		return 'F';
	}
}
