<?php
namespace GeoBoost\Events;

use GeoBoost\Robots\AiBotController;
use GeoBoost\Scan\SiteScanner;

final class BotTracker {

	private EventLogger $logger;

	public function __construct( EventLogger $logger ) {
		$this->logger = $logger;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_log_request' ), 999 );
	}

	public function maybe_log_request(): void {
		$scan = isset( $_SERVER['HTTP_X_GEOBOOST_SCAN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_GEOBOOST_SCAN'] ) ) : '';
		if ( '' !== $scan && SiteScanner::is_scan_request( $scan ) ) {
			return;
		}
		$this->process(
			array(
				'ua'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'path'    => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'referer' => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			)
		);
	}

	public function process( array $req ): void {
		$ua    = (string) ( $req['ua'] ?? '' );
		$match = $this->match( $ua );
		if ( null === $match ) {
			return;
		}
		$this->logger->log(
			'bot_crawl',
			array(
				'bot_or_source' => $match,
				'path'          => (string) ( $req['path'] ?? '' ),
				'status_code'   => 200,
				'user_agent'    => $ua,
				'referer'       => (string) ( $req['referer'] ?? '' ),
			)
		);
	}

	private function match( string $ua ): ?string {
		if ( '' === $ua ) {
			return null;
		}
		foreach ( AiBotController::known_bots() as $bot ) {
			$needle = $bot['user_agent'];
			if ( stripos( $ua, $needle ) !== false ) {
				return $needle;
			}
		}
		return null;
	}
}
