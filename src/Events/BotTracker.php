<?php
namespace GeoBoost\Events;

use GeoBoost\Robots\AiBotController;

final class BotTracker {

	private EventLogger $logger;

	public function __construct( EventLogger $logger ) {
		$this->logger = $logger;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_log_request' ), 999 );
	}

	public function maybe_log_request(): void {
		$this->process( array(
			'ua'      => (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'path'    => (string) ( $_SERVER['REQUEST_URI'] ?? '' ),
			'referer' => (string) ( $_SERVER['HTTP_REFERER'] ?? '' ),
		) );
	}

	public function process( array $req ): void {
		$ua    = (string) ( $req['ua'] ?? '' );
		$match = $this->match( $ua );
		if ( null === $match ) {
			return;
		}
		$this->logger->log( 'bot_crawl', array(
			'bot_or_source' => $match,
			'path'          => (string) ( $req['path'] ?? '' ),
			'status_code'   => 200,
			'user_agent'    => $ua,
			'referer'       => (string) ( $req['referer'] ?? '' ),
		) );
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
