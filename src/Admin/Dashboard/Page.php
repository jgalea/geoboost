<?php
namespace GeoBoost\Admin\Dashboard;

final class Page {

	private const SLUG = 'geoboost';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'geoboost-admin',
			GEOBOOST_URL . 'assets/admin/admin.css',
			array(),
			GEOBOOST_VERSION
		);
		wp_enqueue_style(
			'geoboost-dashboard',
			GEOBOOST_URL . 'assets/admin/dashboard.css',
			array( 'geoboost-admin' ),
			GEOBOOST_VERSION
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$data = ( new DataProvider() )->get();
		( new View() )->render( $data );
	}
}
