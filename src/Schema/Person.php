<?php
namespace GeoBoost\Schema;

final class Person {

	/**
	 * @param array{
	 *   name:string, url:string,
	 *   description?:string, jobTitle?:string,
	 *   sameAs?:array<int,string>, knowsAbout?:array<int,string>,
	 *   image?:string
	 * } $input
	 * @return array<string,mixed>
	 */
	public function build( array $input ): array {
		$out = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => $input['name'],
			'url'      => $input['url'],
		);

		if ( ! empty( $input['description'] ) ) {
			$out['description'] = $input['description'];
		}
		if ( ! empty( $input['jobTitle'] ) ) {
			$out['jobTitle'] = $input['jobTitle'];
		}
		if ( ! empty( $input['image'] ) ) {
			$out['image'] = $input['image'];
		}
		if ( ! empty( $input['sameAs'] ) ) {
			$filtered = array_values( array_filter( (array) $input['sameAs'], static fn( $v ) => is_string( $v ) && '' !== trim( $v ) ) );
			if ( $filtered ) {
				$out['sameAs'] = $filtered;
			}
		}
		if ( ! empty( $input['knowsAbout'] ) ) {
			$filtered = array_values( array_filter( (array) $input['knowsAbout'], static fn( $v ) => is_string( $v ) && '' !== trim( $v ) ) );
			if ( $filtered ) {
				$out['knowsAbout'] = $filtered;
			}
		}
		return $out;
	}
}
