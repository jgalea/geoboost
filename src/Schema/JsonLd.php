<?php
namespace GeoBoost\Schema;

final class JsonLd {

	public static function render( array $data ): string {
		$clean = self::clean( $data );
		if ( empty( $clean ) ) {
			return '';
		}
		$json = wp_json_encode( $clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return '';
		}
		// Prevent early script termination by escaping the sequence.
		$json = str_replace( '</', '<\/', $json );
		return '<script type="application/ld+json">' . $json . '</script>';
	}

	private static function clean( array $data ): array {
		$out = array();
		foreach ( $data as $k => $v ) {
			if ( null === $v || '' === $v || array() === $v ) {
				continue;
			}
			if ( is_array( $v ) ) {
				$v = self::clean( $v );
				if ( empty( $v ) ) {
					continue;
				}
			}
			$out[ $k ] = $v;
		}
		return $out;
	}
}
