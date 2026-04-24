<?php
namespace GeoBoost\Admin\MetaBoxes;

use GeoBoost\Analyzer\ContentAnalyzer;
use GeoBoost\Analyzer\Recommendations;
use GeoBoost\Analyzer\SignalCategories;
use GeoBoost\SeoIntegration\Detector;

final class StatusBox {

	private const META_SIGNALS = '_geoboost_signals';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'recompute' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_box(): void {
		foreach ( array( 'post', 'page' ) as $pt ) {
			add_meta_box(
				'geoboost_status',
				__( 'GEOBoost Status', 'geoboost' ),
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
			'geoboost-statusbox',
			GEOBOOST_URL . 'assets/admin/statusbox.css',
			array(),
			GEOBOOST_VERSION
		);
	}

	public function render( \WP_Post $post ): void {
		$signals = (array) get_post_meta( $post->ID, self::META_SIGNALS, true );

		$essential_failures = array();
		foreach ( SignalCategories::essentials() as $key ) {
			if ( empty( $signals[ $key ] ) ) {
				$essential_failures[] = $key;
			}
		}

		$recs = ( new Recommendations() )->for_signals( $signals );

		echo '<div class="geoboost-statusbox">';

		if ( empty( $essential_failures ) ) {
			printf(
				'<p class="geoboost-statusbox__status geoboost-statusbox__status--ok">%s</p>',
				esc_html__( '✓ Essentials OK', 'geoboost' )
			);
		} else {
			printf(
				'<p class="geoboost-statusbox__status geoboost-statusbox__status--warn">%s</p><ul class="geoboost-statusbox__essentials">',
				esc_html(
					sprintf(
						/* translators: %d: count */
						_n( '⚠ %d essential missing', '⚠ %d essentials missing', count( $essential_failures ), 'geoboost' ),
						count( $essential_failures )
					)
				)
			);
			foreach ( $essential_failures as $key ) {
				echo '<li>' . esc_html( $this->essential_label( $key ) ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $recs ) ) {
			printf(
				'<details class="geoboost-statusbox__recs"><summary>%s</summary><ul>',
				esc_html(
					sprintf(
						/* translators: %d: count */
						_n( '%d suggestion available', '%d suggestions available', count( $recs ), 'geoboost' ),
						count( $recs )
					)
				)
			);
			foreach ( $recs as $rec ) {
				echo '<li>' . esc_html( $rec['text'] ) . '</li>';
			}
			echo '</ul></details>';
		}

		printf(
			'<p class="geoboost-statusbox__footer">%s</p>',
			esc_html__( 'Status updates on save.', 'geoboost' )
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
				'has_article_schema' => $this->article_schema_present( $post ),
				'has_custom_jsonld'  => '' !== trim( (string) get_post_meta( $post_id, '_geoboost_custom_jsonld', true ) ),
				'site_host'          => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);

		update_post_meta( $post_id, self::META_SIGNALS, $signals );
	}

	/**
	 * Article schema is "present" if either GEOBoost or any active SEO plugin
	 * known to emit Article schema is enabled. Config-level check, not a runtime
	 * scrape of the rendered HTML.
	 */
	private function article_schema_present( \WP_Post $post ): bool {
		$detector        = new Detector();
		$active          = $detector->active();
		$schema_emitting = array( 'yoast', 'rankmath', 'aioseo', 'seopress', 'tsf' );
		foreach ( $active as $slug ) {
			if ( in_array( $slug, $schema_emitting, true ) ) {
				return true;
			}
		}
		// GEOBoost own output is enabled when no competing plugin is detected.
		return empty( $active );
	}

	private function essential_label( string $key ): string {
		switch ( $key ) {
			case 'has_article_schema':
				return __( 'Article schema not detected', 'geoboost' );
			case 'heading_hierarchy_ok':
				return __( 'Heading hierarchy broken (H levels skipped)', 'geoboost' );
			case 'word_count_ok':
				return __( 'Content under 150 words', 'geoboost' );
			case 'has_author_eeat':
				return __( 'Author profile missing credentials/expertise', 'geoboost' );
			default:
				return $key;
		}
	}
}
