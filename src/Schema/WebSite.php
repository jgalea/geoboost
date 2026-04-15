<?php
namespace GeoBoost\Schema;

final class WebSite {

	public function build( array $input ): array {
		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'name'            => $input['name'],
			'url'             => $input['url'],
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => rtrim( $input['url'], '/' ) . '/?s={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}
}
