<?php
namespace GeoBoost\Core;

use GeoBoost\Events\EventsTable;

final class Migrations {

	private const OPTION  = 'geoboost_db_version';
	private const CURRENT = '2';

	public static function run(): void {
		$installed = get_option( self::OPTION, '0' );
		if ( version_compare( $installed, self::CURRENT, '>=' ) ) {
			return;
		}
		EventsTable::create();
		update_option( self::OPTION, self::CURRENT );
	}
}
