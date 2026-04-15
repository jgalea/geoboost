<?php
namespace GeoBoost\LlmsTxt;

final class OverrideDetector {

	private const CACHE_KEY = 'geoboost_llms_override';
	private const CACHE_TTL = 900; // 15 min

	/** @var callable|null */
	private $probe = null;

	public function set_probe( callable $probe ): void {
		$this->probe = $probe;
	}

	public function is_overridden( string $url ): bool {
		$result = $this->probe ? ( $this->probe )( $url ) : wp_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
			)
		);

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			return false;
		}
		if ( ! is_array( $result ) ) {
			return false;
		}
		$code = isset( $result['code'] ) ? (int) $result['code'] : ( isset( $result['response']['code'] ) ? (int) $result['response']['code'] : 0 );
		if ( 200 !== $code ) {
			return false;
		}
		$headers = $result['headers'] ?? array();
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}
		$lower = array();
		foreach ( (array) $headers as $k => $v ) {
			$lower[ strtolower( (string) $k ) ] = is_array( $v ) ? implode( ',', $v ) : (string) $v;
		}
		return ! isset( $lower['x-geoboost-source'] ) || 'geoboost' !== $lower['x-geoboost-source'];
	}

	public function check_cached( string $url ): bool {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return (bool) $cached;
		}
		$result = $this->is_overridden( $url );
		set_transient( self::CACHE_KEY, $result ? 1 : 0, self::CACHE_TTL );
		return $result;
	}
}
