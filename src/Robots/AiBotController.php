<?php
namespace GeoBoost\Robots;

use GeoBoost\Core\Settings;

final class AiBotController {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
	}

	/**
	 * Kind is "search" for crawlers that fetch pages to cite in live AI answers,
	 * "training" for crawlers that collect model training data.
	 *
	 * @return array<int,array{user_agent:string,label:string,purpose:string,kind:string}>
	 */
	public static function known_bots(): array {
		return array(
			array(
				'user_agent' => 'GPTBot',
				'label'      => 'OpenAI GPTBot',
				'purpose'    => 'Training data',
				'kind'       => 'training',
			),
			array(
				'user_agent' => 'OAI-SearchBot',
				'label'      => 'OpenAI SearchBot',
				'purpose'    => 'ChatGPT search',
				'kind'       => 'search',
			),
			array(
				'user_agent' => 'ChatGPT-User',
				'label'      => 'ChatGPT User',
				'purpose'    => 'ChatGPT browsing',
				'kind'       => 'search',
			),
			array(
				'user_agent' => 'ClaudeBot',
				'label'      => 'Anthropic ClaudeBot',
				'purpose'    => 'Anthropic training',
				'kind'       => 'training',
			),
			array(
				'user_agent' => 'anthropic-ai',
				'label'      => 'Anthropic (legacy)',
				'purpose'    => 'Legacy UA',
				'kind'       => 'training',
			),
			array(
				'user_agent' => 'Google-Extended',
				'label'      => 'Google Extended (Gemini)',
				'purpose'    => 'Gemini training',
				'kind'       => 'training',
			),
			array(
				'user_agent' => 'PerplexityBot',
				'label'      => 'Perplexity',
				'purpose'    => 'Perplexity answers',
				'kind'       => 'search',
			),
			array(
				'user_agent' => 'CCBot',
				'label'      => 'Common Crawl',
				'purpose'    => 'Dataset feeding many LLMs',
				'kind'       => 'training',
			),
			array(
				'user_agent' => 'Applebot-Extended',
				'label'      => 'Apple Intelligence',
				'purpose'    => 'Apple training',
				'kind'       => 'training',
			),
			array(
				'user_agent' => 'Meta-ExternalAgent',
				'label'      => 'Meta AI',
				'purpose'    => 'Meta training',
				'kind'       => 'training',
			),
			array(
				'user_agent' => 'Bytespider',
				'label'      => 'ByteDance Bytespider',
				'purpose'    => 'ByteDance training',
				'kind'       => 'training',
			),
		);
	}

	/**
	 * @param array<int,string> $blocked
	 * @return array<int,array{user_agent:string,label:string,purpose:string,kind:string}>
	 */
	public static function blocked_of_kind( array $blocked, string $kind ): array {
		return array_values(
			array_filter(
				self::known_bots(),
				static function ( array $bot ) use ( $blocked, $kind ): bool {
					return $kind === $bot['kind'] && in_array( $bot['user_agent'], $blocked, true );
				}
			)
		);
	}

	/**
	 * @param string|bool $site_public 1 if blog_public=1, 0 otherwise (WP passes as boolean-ish string).
	 */
	public function filter_robots_txt( string $output, $site_public ): string {
		if ( ! $site_public ) {
			return $output;
		}

		$blocked = (array) $this->settings->get( 'blocked_ai_bots', array() );
		if ( empty( $blocked ) ) {
			return $output;
		}

		$known = array_column( self::known_bots(), 'user_agent' );
		$valid = array_values( array_intersect( $blocked, $known ) );

		if ( empty( $valid ) ) {
			return $output;
		}

		$append = "\n\n# GEOBoost: AI crawler blocks\n";
		foreach ( $valid as $ua ) {
			$append .= "User-agent: {$ua}\nDisallow: /\n\n";
		}

		return rtrim( $output, "\n" ) . $append;
	}
}
