<?php
namespace GeoBoost\Events;

final class ReferralTracker {

	private const HOST_MAP = array(
		'chat.openai.com'       => 'chatgpt',
		'chatgpt.com'           => 'chatgpt',
		'claude.ai'             => 'claude',
		'claude.com'            => 'claude',
		'gemini.google.com'     => 'gemini',
		'bard.google.com'       => 'gemini',
		'perplexity.ai'         => 'perplexity',
		'www.perplexity.ai'     => 'perplexity',
		'copilot.microsoft.com' => 'copilot',
		'you.com'               => 'you',
		'phind.com'             => 'phind',
	);

	private const UTM_ALLOW = array( 'chatgpt', 'claude', 'gemini', 'perplexity', 'copilot', 'you', 'phind' );

	private EventLogger $logger;

	public function __construct( EventLogger $logger ) {
		$this->logger = $logger;
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_log_request' ), 1 );
	}

	public function maybe_log_request(): void {
		$this->process( array(
			'referer'    => (string) ( $_SERVER['HTTP_REFERER'] ?? '' ),
			'path'       => (string) ( $_SERVER['REQUEST_URI'] ?? '' ),
			'utm_source' => (string) ( $_GET['utm_source'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) );
	}

	public function process( array $req ): void {
		$source = $this->detect( (string) ( $req['referer'] ?? '' ), (string) ( $req['utm_source'] ?? '' ) );
		if ( null === $source ) {
			return;
		}
		$this->logger->log( 'ai_referral', array(
			'bot_or_source' => $source,
			'path'          => (string) ( $req['path'] ?? '' ),
			'status_code'   => 200,
			'user_agent'    => '',
			'referer'       => (string) ( $req['referer'] ?? '' ),
		) );
	}

	private function detect( string $referer, string $utm ): ?string {
		$utm = strtolower( trim( $utm ) );
		if ( in_array( $utm, self::UTM_ALLOW, true ) ) {
			return $utm;
		}
		if ( '' === $referer ) {
			return null;
		}
		$host = strtolower( (string) parse_url( $referer, PHP_URL_HOST ) );
		return self::HOST_MAP[ $host ] ?? null;
	}
}
