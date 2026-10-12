<?php
namespace GeoBoost\Core;

use GeoBoost\LlmsTxt\Cache;
use GeoBoost\LlmsTxt\Endpoint;
use GeoBoost\LlmsTxt\Generator;

final class Activator {
	public static function activate(): void {
		\GeoBoost\Core\Migrations::run();
		\GeoBoost\Events\Pruner::schedule();
		// Activation runs after init, so the rule isn't registered yet on a fresh install.
		( new Endpoint( new Generator(), new Cache() ) )->add_rewrite();
		flush_rewrite_rules( false );
	}
}
