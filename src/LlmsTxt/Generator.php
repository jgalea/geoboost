<?php
namespace GeoBoost\LlmsTxt;

final class Generator {

	/**
	 * @param array{site_title:string,tagline:string,description:string,home_url:string} $site
	 * @param array<int,array{title:string,url:string,excerpt:string}>                   $posts
	 */
	public function build( array $site, array $posts, array $extras = array() ): string {
		$lines = array();

		$lines[] = '# ' . $site['site_title'];
		$lines[] = '';

		if ( ! empty( $site['tagline'] ) ) {
			$lines[] = '> ' . $site['tagline'];
			$lines[] = '';
		}
		if ( ! empty( $site['description'] ) ) {
			$lines[] = $site['description'];
			$lines[] = '';
		}

		if ( ! empty( $extras['key_pages'] ) ) {
			$lines[] = '## Key Pages';
			$lines[] = '';
			foreach ( $extras['key_pages'] as $p ) {
				$title   = $this->flatten_whitespace( str_replace( array( '[', ']' ), ' ', $p['title'] ) );
				$url     = $this->sanitize_url( $p['url'] );
				$summary = $this->flatten_whitespace( (string) ( $p['summary'] ?? '' ) );
				$entry   = sprintf( '- [%s](%s)', $title, $url );
				if ( '' !== $summary ) {
					$entry .= ': ' . $summary;
				}
				$lines[] = $entry;
			}
			$lines[] = '';
		}

		if ( ! empty( $posts ) ) {
			$lines[] = '## Posts';
			$lines[] = '';
			foreach ( $posts as $post ) {
				$title   = $this->flatten_whitespace( str_replace( array( '[', ']' ), ' ', $post['title'] ) );
				$url     = $this->sanitize_url( $post['url'] );
				$excerpt = $this->flatten_whitespace( $post['excerpt'] );
				$entry   = sprintf( '- [%s](%s)', $title, $url );
				if ( '' !== $excerpt ) {
					$entry .= ': ' . $excerpt;
				}
				$lines[] = $entry;
			}
			$lines[] = '';
		}

		if ( ! empty( $extras['authors'] ) ) {
			$lines[] = '## Authors';
			$lines[] = '';
			foreach ( $extras['authors'] as $a ) {
				$name  = $this->flatten_whitespace( $a['name'] );
				$url   = $this->sanitize_url( $a['url'] );
				$bio   = $this->flatten_whitespace( (string) ( $a['bio'] ?? '' ) );
				$entry = sprintf( '- [%s](%s)', $name, $url );
				if ( '' !== $bio ) {
					$entry .= ': ' . $bio;
				}
				$lines[] = $entry;
			}
			$lines[] = '';
		}

		return implode( "\n", $lines );
	}

	private function flatten_whitespace( string $text ): string {
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	private function sanitize_url( string $url ): string {
		if ( function_exists( 'esc_url_raw' ) ) {
			return esc_url_raw( $url );
		}
		return trim( str_replace( array( ' ', '"', '<', '>', '`', "\n", "\r", "\t" ), '', $url ) );
	}
}
