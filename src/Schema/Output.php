<?php
namespace GeoBoost\Schema;

use GeoBoost\SeoIntegration\Detector;

final class Output {

	private Detector $detector;
	private string $captured = '';

	public function __construct( Detector $detector ) {
		$this->detector = $detector;
	}

	public function register(): void {
		add_action( 'wp_head', array( $this, 'start_capture' ), 1 );
		add_action( 'wp_head', array( $this, 'emit' ), 20 );
	}

	public function start_capture(): void {
		ob_start( array( $this, 'capture_filter' ) );
	}

	public function capture_filter( string $buffer ): string {
		$this->captured .= $buffer;
		return $buffer;
	}

	public function emit(): void {
		// Flush the capture buffer so prior head output is still rendered.
		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		$pieces = array();

		if ( is_singular( array( 'post', 'page' ) ) ) {
			$pieces[] = $this->maybe_article();
			$pieces[] = $this->custom_jsonld();
		}
		if ( is_author() ) {
			$pieces[] = $this->maybe_person_archive();
		}
		if ( is_front_page() ) {
			$pieces[] = $this->maybe_website();
			$pieces[] = $this->maybe_organization();
		}

		foreach ( array_filter( $pieces ) as $p ) {
			echo $p . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private function should_emit( string $type ): bool {
		$mode = (string) get_option( 'geoboost_schema_mode_' . strtolower( $type ), 'auto' );
		if ( 'off' === $mode ) {
			return false;
		}
		if ( 'force' === $mode ) {
			return true;
		}
		// auto: skip if another plugin already emits it.
		if ( $this->detector->active() && $this->detector->html_emits_type( $this->captured, $type ) ) {
			return false;
		}
		return true;
	}

	private function maybe_article(): string {
		if ( ! $this->should_emit( 'Article' ) ) {
			return '';
		}
		global $post;
		if ( ! $post ) {
			return '';
		}
		$author_id = (int) $post->post_author;
		$data      = ( new Article() )->build(
			array(
				'title'          => get_the_title( $post ),
				'url'            => (string) get_permalink( $post ),
				'excerpt'        => wp_strip_all_tags( (string) get_the_excerpt( $post ) ),
				'content_html'   => (string) apply_filters( 'the_content', $post->post_content ),
				'date_published' => (string) get_the_date( 'c', $post ),
				'date_modified'  => (string) get_the_modified_date( 'c', $post ),
				'author_name'    => get_the_author_meta( 'display_name', $author_id ),
				'author_url'     => get_author_posts_url( $author_id ),
				'site_name'      => get_bloginfo( 'name' ),
				'site_url'       => home_url( '/' ),
				'image_url'      => (string) get_the_post_thumbnail_url( $post, 'full' ),
			)
		);
		return JsonLd::render( $data );
	}

	private function maybe_person_archive(): string {
		if ( ! $this->should_emit( 'Person' ) ) {
			return '';
		}
		$u = get_queried_object();
		if ( ! $u || empty( $u->ID ) ) {
			return '';
		}
		$author = ( new \GeoBoost\Author\AuthorSchema() )->build_from_user( (int) $u->ID );
		return JsonLd::render( $author );
	}

	private function maybe_website(): string {
		if ( ! $this->should_emit( 'WebSite' ) ) {
			return '';
		}
		return JsonLd::render(
			( new WebSite() )->build(
				array(
					'name' => get_bloginfo( 'name' ),
					'url'  => home_url( '/' ),
				)
			)
		);
	}

	private function maybe_organization(): string {
		if ( ! $this->should_emit( 'Organization' ) ) {
			return '';
		}
		return JsonLd::render(
			( new Organization() )->build(
				array(
					'name' => get_bloginfo( 'name' ),
					'url'  => home_url( '/' ),
					'logo' => (string) get_site_icon_url( 512 ),
				)
			)
		);
	}

	private function custom_jsonld(): string {
		global $post;
		if ( ! $post ) {
			return '';
		}
		$raw = (string) get_post_meta( $post->ID, '_geoboost_custom_jsonld', true );
		if ( '' === trim( $raw ) ) {
			return '';
		}
		// Already validated on save; trust it but wrap in script tag.
		$json = trim( $raw );
		$json = str_replace( '</', '<\/', $json );
		return '<script type="application/ld+json">' . $json . '</script>';
	}
}
