<?php
namespace GeoBoost\Analyzer;

final class ContentAnalyzer {

	/**
	 * @param array $input
	 * @return array<string,bool|int>
	 */
	public function analyze( array $input ): array {
		$html      = (string) ( $input['content_html'] ?? '' );
		$text      = trim( preg_replace( '/\s+/', ' ', strip_tags( $html ) ) );
		$words     = '' === $text ? 0 : count( preg_split( '/\s+/', $text ) );
		$site_host = (string) ( $input['site_host'] ?? '' );

		return array(
			'word_count'           => $words,
			'word_count_ok'        => $words >= 300 && $words <= 3000,
			'has_summary'          => false !== strpos( $html, 'wp-block-geoboost-summary' ) || false !== stripos( $html, 'itemprop="abstract"' ) || ! empty( $input['excerpt'] ),
			'has_key_facts'        => false !== strpos( $html, 'wp-block-geoboost-key-facts' ) || ( false !== strpos( $html, '<dl' ) && false !== strpos( $html, '<dt' ) ),
			'has_faq_signal'       => (bool) preg_match( '/<h[234][^>]*>(how|what|why|when|where|who)\b[^<]*\?/i', $html ) || false !== strpos( $html, 'wp-block-geoboost-faq' ),
			'heading_hierarchy_ok' => $this->headings_ok( $html ),
			'has_featured'         => (bool) ( $input['has_featured'] ?? false ),
			'has_article_schema'   => (bool) ( $input['has_article_schema'] ?? false ),
			'has_custom_jsonld'    => (bool) ( $input['has_custom_jsonld'] ?? false ),
			'has_author_eeat'      => ! empty( $input['author_fields']['credentials'] ?? '' ) || ! empty( $input['author_fields']['knowsAbout'] ?? array() ),
			'has_outbound_link'    => $this->has_outbound( $html, $site_host ),
			'has_internal_link'    => $this->has_internal( $html, $site_host ),
		);
	}

	private function headings_ok( string $html ): bool {
		if ( ! preg_match_all( '/<h([1-6])/i', $html, $m ) ) {
			return true; // no headings is technically fine
		}
		$levels = array_map( 'intval', $m[1] );
		$prev   = 0;
		foreach ( $levels as $lvl ) {
			if ( $prev && $lvl > $prev + 1 ) {
				return false;
			}
			$prev = $lvl;
		}
		return true;
	}

	private function has_outbound( string $html, string $site_host ): bool {
		if ( ! preg_match_all( '/href=["\']https?:\/\/([^\/"\']+)/i', $html, $m ) ) {
			return false;
		}
		foreach ( $m[1] as $host ) {
			if ( '' !== $site_host && stripos( $host, $site_host ) === false ) {
				return true;
			}
		}
		return false;
	}

	private function has_internal( string $html, string $site_host ): bool {
		if ( preg_match( '/href=["\']\/[^\/"\']/', $html ) ) {
			return true;
		}
		if ( '' !== $site_host && preg_match( '#href=["\']https?://' . preg_quote( $site_host, '#' ) . '#i', $html ) ) {
			return true;
		}
		return false;
	}
}
