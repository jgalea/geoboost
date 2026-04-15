<?php
namespace GeoBoost\Admin\Dashboard;

final class Page {

	private const SLUG = 'geoboost';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'GEOBoost', 'geoboost' ),
			__( 'GEOBoost', 'geoboost' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-admin-site-alt3',
			81
		);
		add_submenu_page(
			self::SLUG,
			__( 'Dashboard', 'geoboost' ),
			__( 'Dashboard', 'geoboost' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap geoboost-dashboard"><h1>' . esc_html__( 'GEOBoost Dashboard', 'geoboost' ) . '</h1>';
		echo '<div class="geoboost-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:2em;margin-top:1em">';
		echo '<div>' . ( new ScoreWidget() )->render() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div>' . ( new BotWidget() )->render() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div>' . ( new ReferralWidget() )->render() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div>' . ( new RecommendationsWidget() )->render() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></div>';
	}
}
