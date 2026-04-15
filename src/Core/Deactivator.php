<?php
namespace GeoBoost\Core;

final class Deactivator {
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
