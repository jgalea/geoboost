<?php
namespace GeoBoost\Core;

final class Deactivator {
	public static function deactivate(): void {
		\GeoBoost\Events\Pruner::unschedule();
		flush_rewrite_rules( false );
	}
}
