<?php
namespace GeoBoost\LlmsTxt;

final class Endpoint {

	private const QUERY_VAR = 'geoboost_llms';

	private Generator $generator;
	private Cache $cache;

	public function __construct( Generator $generator, Cache $cache ) {
		$this->generator = $generator;
		$this->cache     = $cache;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ) );
		add_action( 'save_post', array( $this, 'on_save_post' ) );
	}

	public function add_rewrite(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_serve(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$content = $this->cache->get();
		if ( null === $content ) {
			$content = $this->generate();
			$this->cache->set( $content );
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-GeoBoost-Source: geoboost' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function on_save_post( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$this->cache->flush();
	}

	private function generate(): string {
		$description_override = (string) get_option( 'geoboost_description', '' );
		$site                 = array(
			'site_title'  => get_bloginfo( 'name' ),
			'tagline'     => get_bloginfo( 'description' ),
			'description' => '' !== $description_override ? $description_override : '',
			'home_url'    => home_url( '/' ),
		);

		$post_types = array();
		if ( get_option( 'geoboost_include_posts', true ) ) {
			$post_types[] = 'post';
		}
		if ( get_option( 'geoboost_include_pages', true ) ) {
			$post_types[] = 'page';
		}

		$query_args = array(
			'post_type'      => $post_types ? $post_types : array( 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query_args = (array) apply_filters( 'geoboost_llms_query_args', $query_args );

		$posts = array();
		foreach ( get_posts( $query_args ) as $p ) {
			$posts[] = array(
				'title'   => get_the_title( $p ),
				'url'     => (string) get_permalink( $p ),
				'excerpt' => $this->excerpt( $p ),
			);
		}

		$key_pages = array();
		if ( function_exists( 'get_posts' ) ) {
			$top_posts = get_posts(
				array(
					'post_type'      => 'post',
					'posts_per_page' => 10,
					'meta_key'       => '_geoboost_score', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
				)
			);
			foreach ( $top_posts as $p ) {
				$summary = (string) get_post_meta( $p->ID, '_geoboost_llmstxt_summary', true );
				if ( '' === $summary ) {
					$summary = (string) get_the_excerpt( $p );
				}
				$key_pages[] = array(
					'title'   => get_the_title( $p ),
					'url'     => (string) get_permalink( $p ),
					'summary' => wp_strip_all_tags( $summary ),
				);
			}
		}

		$authors = array();
		foreach ( get_users( array( 'who' => 'authors', 'number' => 20 ) ) as $u ) {
			$credentials = (string) get_user_meta( $u->ID, 'geoboost_credentials', true );
			$authors[]   = array(
				'name' => (string) $u->display_name,
				'url'  => (string) get_author_posts_url( $u->ID ),
				'bio'  => $credentials ?: (string) get_user_meta( $u->ID, 'description', true ),
			);
		}

		$extras = array(
			'key_pages' => $key_pages,
			'authors'   => $authors,
		);

		$posts_filtered = array();
		foreach ( $posts as $p ) {
			$post_id  = function_exists( 'url_to_postid' ) ? url_to_postid( $p['url'] ) : 0;
			$excluded = '1' === (string) get_post_meta( $post_id, '_geoboost_llmstxt_exclude', true );
			if ( ! $excluded ) {
				$posts_filtered[] = $p;
			}
		}
		$posts = (array) apply_filters( 'geoboost_llms_posts', $posts_filtered );

		return $this->generator->build( $site, $posts, $extras );
	}

	private function excerpt( \WP_Post $post ): string {
		$raw = $post->post_excerpt;
		if ( '' === trim( $raw ) ) {
			$raw = wp_strip_all_tags( $post->post_content );
			$raw = wp_trim_words( $raw, 30, '' );
		}
		return trim( $raw );
	}
}
