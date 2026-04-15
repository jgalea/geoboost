<?php
namespace GeoBoost\Core;

final class Activator {
	public static function activate(): void {
		\GeoBoost\Core\Migrations::run();
		flush_rewrite_rules( false );
	}
}
