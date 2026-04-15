<?php
namespace GeoBoost\Schema;

final class Organization {

	public function build( array $input ): array {
		$out = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => $input['name'],
			'url'      => $input['url'],
		);
		if ( ! empty( $input['logo'] ) ) {
			$out['logo'] = $input['logo'];
		}
		if ( ! empty( $input['sameAs'] ) ) {
			$out['sameAs'] = array_values( array_filter( (array) $input['sameAs'] ) );
		}
		return $out;
	}
}
