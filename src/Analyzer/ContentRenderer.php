<?php
namespace GeoBoost\Analyzer;

/**
 * Renders post content for analysis without applying the_content, so other
 * plugins' content filters (related posts, ads, share buttons) don't run
 * outside the loop or skew the score. Blocks and GEOBoost's own shortcodes
 * render; every other shortcode is stripped.
 */
final class ContentRenderer {

	private const OWN_SHORTCODES = array( 'geoboost_summary', 'geoboost_key_facts', 'geoboost_faq' );

	public static function render( string $content ): string {
		$content = do_blocks( $content );

		$keep_own = static function ( array $tags ): array {
			return array_diff( $tags, self::OWN_SHORTCODES );
		};
		add_filter( 'strip_shortcodes_tagnames', $keep_own );
		$content = strip_shortcodes( $content );
		remove_filter( 'strip_shortcodes_tagnames', $keep_own );

		return do_shortcode( $content );
	}
}
