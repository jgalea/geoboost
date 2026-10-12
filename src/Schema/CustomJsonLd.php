<?php
namespace GeoBoost\Schema;

final class CustomJsonLd {

	/**
	 * @return array{ok:bool,error:string}
	 */
	public function validate( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array(
				'ok'    => true,
				'error' => '',
			);
		}
		$data = json_decode( $raw, true );
		if ( null === $data ) {
			return array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: JSON parser error message */
					__( 'Invalid JSON: %s', 'geoboost' ),
					json_last_error_msg()
				),
			);
		}
		if ( ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'JSON-LD must be an object.', 'geoboost' ),
			);
		}
		if ( ! isset( $data['@context'] ) || ! isset( $data['@type'] ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'JSON-LD must include @context and @type.', 'geoboost' ),
			);
		}
		return array(
			'ok'    => true,
			'error' => '',
		);
	}

	/**
	 * Re-encodes JSON so it can't break out of the script tag it's printed in.
	 * Returns '' for anything that isn't a JSON object or array.
	 */
	public function normalize( string $raw ): string {
		$data = json_decode( trim( $raw ) );
		if ( ! is_object( $data ) && ! is_array( $data ) ) {
			return '';
		}
		$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return false === $json ? '' : $json;
	}
}
