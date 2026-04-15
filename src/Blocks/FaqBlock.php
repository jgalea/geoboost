<?php
namespace GeoBoost\Blocks;

use GeoBoost\Schema\JsonLd;

final class FaqBlock {

	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'render_block_geoboost/faq', array( $this, 'inject_schema' ), 10, 2 );
	}

	public function register_block(): void {
		register_block_type( GEOBOOST_DIR . 'build/faq' );
	}

	public function inject_schema( string $block_content, array $block ): string {
		$items = $block['attrs']['items'] ?? array();
		$items = array_values( array_filter( (array) $items, static fn( $i ) => ! empty( $i['q'] ) && ! empty( $i['a'] ) ) );
		if ( empty( $items ) ) {
			return $block_content;
		}
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(
				static fn( array $it ) => array(
					'@type'          => 'Question',
					'name'           => $it['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $it['a'] ),
					),
				),
				$items
			),
		);
		return $block_content . JsonLd::render( $data );
	}
}
