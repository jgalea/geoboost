<?php
namespace GeoBoost\LlmsTxt;

final class Cache {

	private const KEY = 'geoboost_llms_txt';
	private const TTL = 12 * HOUR_IN_SECONDS;

	/** @var callable|null */
	private static $getter = null;
	/** @var callable|null */
	private static $setter = null;
	/** @var callable|null */
	private static $deleter = null;

	public function get(): ?string {
		$getter = self::$getter ?? 'get_transient';
		$value  = $getter( self::KEY );
		return false === $value ? null : (string) $value;
	}

	public function set( string $content ): void {
		$setter = self::$setter ?? 'set_transient';
		$setter( self::KEY, $content, defined( 'HOUR_IN_SECONDS' ) ? self::TTL : 43200 );
	}

	public function flush(): void {
		$deleter = self::$deleter ?? 'delete_transient';
		$deleter( self::KEY );
	}

	public static function set_test_accessors( ?callable $getter, ?callable $setter, ?callable $deleter ): void {
		self::$getter  = $getter;
		self::$setter  = $setter;
		self::$deleter = $deleter;
	}
}
