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
				'error' => 'Invalid JSON: ' . json_last_error_msg(),
			);
		}
		if ( ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'error' => 'JSON-LD must be an object.',
			);
		}
		if ( ! isset( $data['@context'] ) || ! isset( $data['@type'] ) ) {
			return array(
				'ok'    => false,
				'error' => 'JSON-LD must include @context and @type.',
			);
		}
		return array(
			'ok'    => true,
			'error' => '',
		);
	}
}
