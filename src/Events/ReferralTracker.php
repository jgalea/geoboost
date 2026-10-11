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
		$this->process(
			array(
				'referer'    => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'path'       => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tracking of a public query arg.
				'utm_source' => isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : '',
			)
		);
	}

	public function process( array $req ): void {
		$raw_referer = (string) ( $req['referer'] ?? '' );
		$source      = $this->detect( $raw_referer, (string) ( $req['utm_source'] ?? '' ) );
		if ( null === $source ) {
			return;
		}
		$parsed  = wp_parse_url( $raw_referer );
		$referer = ( isset( $parsed['scheme'], $parsed['host'] ) )
			? $parsed['scheme'] . '://' . $parsed['host']
			: '';
		$this->logger->log(
			'ai_referral',
			array(
				'bot_or_source' => $source,
				'path'          => (string) ( $req['path'] ?? '' ),
				'status_code'   => 200,
				'user_agent'    => '',
				'referer'       => $referer,
			)
		);
	}

	private function detect( string $referer, string $utm ): ?string {
		$utm = strtolower( trim( $utm ) );
		if ( in_array( $utm, self::UTM_ALLOW, true ) ) {
			return $utm;
		}
		if ( '' === $referer ) {
			return null;
		}
		$host = strtolower( (string) wp_parse_url( $referer, PHP_URL_HOST ) );
		return self::HOST_MAP[ $host ] ?? null;
	}
}
