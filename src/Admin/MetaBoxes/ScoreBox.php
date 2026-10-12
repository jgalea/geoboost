<?php
namespace GeoBoost\Admin\MetaBoxes;

use GeoBoost\Analyzer\ContentAnalyzer;
use GeoBoost\Analyzer\ContentRenderer;
use GeoBoost\Analyzer\Recommendations;
use GeoBoost\Analyzer\Scorer;

final class ScoreBox {

	private const META_SCORE   = '_geoboost_score';
	private const META_DETAILS = '_geoboost_score_details';
	private const META_HISTORY = '_geoboost_score_history';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'recompute' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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

	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		wp_enqueue_style(
			'geoboost-scorebox',
			GEOBOOST_URL . 'assets/admin/scorebox.css',
			array(),
			GEOBOOST_VERSION
		);
	}

	public function render( \WP_Post $post ): void {
		$score   = (int) get_post_meta( $post->ID, self::META_SCORE, true );
		$details = (array) get_post_meta( $post->ID, self::META_DETAILS, true );
		$tier    = $this->tier( $score );
		$grade   = $this->grade( $score );
		$note    = $this->note( $score );
		printf(
			'<div class="geoboost-scorebox geoboost-scorebox--tier-%s" style="--score:%d">',
			esc_attr( $tier ),
			(int) max( 0, min( 100, (int) $score ) )
		);
		echo '<div class="geoboost-scorebox__hero">';
		printf(
			'<div class="geoboost-scorebox__ring" role="img" aria-label="%s"><span class="geoboost-scorebox__ring-value">%d</span></div>',
			esc_attr(
				sprintf(
					/* translators: %d: score */
					__( 'GEO score %d of 100', 'geoboost' ),
					(int) $score
				)
			),
			(int) $score
		);
		echo '<div class="geoboost-scorebox__meta">';
		printf( '<div class="geoboost-scorebox__grade">%s</div>', esc_html( $grade ) );
		printf( '<div class="geoboost-scorebox__note">%s</div>', esc_html( $note ) );
		echo '</div></div>';

		$recs = ( new Recommendations() )->for_signals( $details );
		if ( ! empty( $recs ) ) {
			printf(
				'<p class="geoboost-scorebox__list-title">%s</p><ul class="geoboost-scorebox__list">',
				esc_html__( 'Quick wins', 'geoboost' )
			);
			foreach ( $recs as $r ) {
				echo '<li>' . esc_html( $r ) . '</li>';
			}
			echo '</ul>';
		}
		printf(
			'<p class="geoboost-scorebox__footer">%s</p>',
			esc_html__( 'Score updates on save.', 'geoboost' )
		);
		echo '</div>';
	}

	public function recompute( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'draft' !== $post->post_status && 'private' !== $post->post_status ) {
			return;
		}
		$author_id         = (int) $post->post_author;
		$author_fields     = array(
			'credentials' => (string) get_user_meta( $author_id, 'geoboost_credentials', true ),
			'knowsAbout'  => (array) get_user_meta( $author_id, 'geoboost_expertise', true ),
		);
		$signals           = ( new ContentAnalyzer() )->analyze(
			array(
				'title'              => $post->post_title,
				'content_html'       => ContentRenderer::render( (string) $post->post_content ),
				'excerpt'            => (string) $post->post_excerpt,
				'author_fields'      => $author_fields,
				'has_featured'       => has_post_thumbnail( $post_id ),
				'has_article_schema' => true,
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

	private function tier( int $score ): string {
		if ( $score >= 80 ) {
			return 'excellent';
		}
		if ( $score >= 60 ) {
			return 'good';
		}
		if ( $score >= 40 ) {
			return 'fair';
		}
		return 'poor';
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

	private function note( int $score ): string {
		if ( $score >= 80 ) {
			return __( 'Cite-ready. Excellent AI visibility signals.', 'geoboost' );
		}
		if ( $score >= 60 ) {
			return __( 'Solid foundation. A few tweaks away from AI-optimized.', 'geoboost' );
		}
		if ( $score >= 40 ) {
			return __( 'Needs attention. AI engines may skip this page.', 'geoboost' );
		}
		return __( 'Low visibility. Add the blocks and signals below.', 'geoboost' );
	}
}
