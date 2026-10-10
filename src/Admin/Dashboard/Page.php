<?php
namespace GeoBoost\Admin\Dashboard;

use GeoBoost\Scan\SiteScanner;

final class Page {

	private const SLUG = 'geoboost';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_geoboost_site_scan', array( $this, 'handle_site_scan' ) );
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

	public function handle_site_scan(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'geoboost' ), 403 );
		}
		check_admin_referer( 'geoboost_site_scan' );

		$input  = isset( $_POST['geoboost_scan_url'] ) ? esc_url_raw( wp_unslash( $_POST['geoboost_scan_url'] ) ) : '';
		$target = SiteScanner::normalize_target( $input, home_url( '/' ) );
		$args   = array( 'page' => self::SLUG );
		if ( null === $target ) {
			$args['scan'] = 'invalid';
		} else {
			$result = ( new SiteScanner() )->run( $target, (bool) get_option( 'blog_public' ) );
			update_option( SiteScanner::OPTION, $result, false );
			DataProvider::flush();
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) . '#geoboost-site-scan' );
		exit;
	}
}
