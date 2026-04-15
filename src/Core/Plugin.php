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
		\GeoBoost\Core\Migrations::run();

		$settings = new \GeoBoost\Core\Settings();

		( new \GeoBoost\LlmsTxt\Endpoint(
			new \GeoBoost\LlmsTxt\Generator(),
			new \GeoBoost\LlmsTxt\Cache()
		) )->register();

		( new \GeoBoost\Robots\AiBotController( $settings ) )->register();

		( new \GeoBoost\Schema\Output( new \GeoBoost\SeoIntegration\Detector() ) )->register();

		( new \GeoBoost\Blocks\FaqBlock() )->register();
		( new \GeoBoost\Blocks\SummaryBlock() )->register();
		( new \GeoBoost\Blocks\KeyFactsBlock() )->register();
		( new \GeoBoost\Blocks\Shortcodes() )->register();

		add_filter(
			'block_categories_all',
			static function ( array $cats ): array {
				array_unshift(
					$cats,
					array(
						'slug'  => 'geoboost',
						'title' => __( 'GEOBoost', 'geoboost' ),
						'icon'  => 'admin-site-alt3',
					)
				);
				return $cats;
			}
		);

		if ( is_admin() ) {
			( new \GeoBoost\Admin\SettingsPage() )->register();
			( new \GeoBoost\Admin\MetaBoxes\CustomJsonLdBox() )->register();
			( new \GeoBoost\Admin\MetaBoxes\ScoreBox() )->register();
			( new \GeoBoost\Author\ProfileFields() )->register();
		}
	}
}
