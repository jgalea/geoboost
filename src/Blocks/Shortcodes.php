<?php
namespace GeoBoost\Blocks;

final class Shortcodes {

	public function register(): void {
		add_shortcode( 'geoboost_summary', array( $this, 'summary' ) );
		add_shortcode( 'geoboost_key_facts', array( $this, 'key_facts' ) );
		add_shortcode( 'geoboost_faq', array( $this, 'faq' ) );
	}

	public function summary( $atts, $content = '' ): string {
		$text = trim( wp_strip_all_tags( (string) $content ) );
		if ( '' === $text ) {
			return '';
		}
		return '<div class="wp-block-geoboost-summary" itemprop="abstract"><strong>' .
			esc_html__( 'TL;DR', 'geoboost' ) . '</strong><p>' . esc_html( $text ) . '</p></div>';
	}

	public function key_facts( $atts, $content = '' ): string {
		$lines = array_filter( array_map( 'trim', explode( "\n", wp_strip_all_tags( (string) $content ) ) ) );
		$out   = array();
		foreach ( $lines as $line ) {
			if ( strpos( $line, '|' ) === false ) {
				continue;
			}
			[ $k, $v ] = array_map( 'trim', explode( '|', $line, 2 ) );
			$out[]     = '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd>';
		}
		if ( empty( $out ) ) {
			return '';
		}
		return '<div class="wp-block-geoboost-key-facts"><strong>' . esc_html__( 'Key Facts', 'geoboost' ) . '</strong><dl>' . implode( '', $out ) . '</dl></div>';
	}

	public function faq( $atts, $content = '' ): string {
		$pairs = array_filter( array_map( 'trim', explode( "\n\n", (string) $content ) ) );
		$items = array();
		foreach ( $pairs as $pair ) {
			$parts = explode( "\n", $pair, 2 );
			if ( count( $parts ) === 2 ) {
				$items[] = '<details><summary>' . esc_html( trim( $parts[0] ) ) . '</summary><div>' . wp_kses_post( trim( $parts[1] ) ) . '</div></details>';
			}
		}
		if ( empty( $items ) ) {
			return '';
		}
		return '<div class="wp-block-geoboost-faq">' . implode( '', $items ) . '</div>';
	}
}
