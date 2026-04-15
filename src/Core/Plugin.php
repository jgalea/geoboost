<?php
namespace GeoBoost\Core;

final class Plugin {

	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		$settings = new \GeoBoost\Core\Settings();

		( new \GeoBoost\LlmsTxt\Endpoint(
			new \GeoBoost\LlmsTxt\Generator(),
			new \GeoBoost\LlmsTxt\Cache()
		) )->register();

		( new \GeoBoost\Robots\AiBotController( $settings ) )->register();

		if ( is_admin() ) {
			( new \GeoBoost\Admin\SettingsPage() )->register();
		}
	}
}
