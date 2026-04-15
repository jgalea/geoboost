<?php
namespace GeoBoost\Core;

final class Settings {

	private const PREFIX = 'geoboost_';

	/** @var callable|null */
	private static $getter = null;
	/** @var callable|null */
	private static $setter = null;

	public function get( string $key, $default = null ) {
		$getter = self::$getter ?? 'get_option';
		return $getter( self::PREFIX . $key, $default );
	}

	public function set( string $key, $value ): void {
		$setter = self::$setter ?? 'update_option';
		$setter( self::PREFIX . $key, $value );
	}

	/**
	 * Test seam — only used from PHPUnit.
	 */
	public static function set_test_accessors( ?callable $getter, ?callable $setter ): void {
		self::$getter = $getter;
		self::$setter = $setter;
	}
}
