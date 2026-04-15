<?php
namespace GeoBoost\Core;

final class Activator {
	public static function activate(): void {
		// The rewrite rule itself is added on init; we just need to flush.
		// Endpoint class re-adds the rule on every init, so flushing now
		// after requiring the plugin file is safe.
		flush_rewrite_rules( false );
	}
}
