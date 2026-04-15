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
	 * @return array<int,array{user_agent:string,label:string,purpose:string}>
	 */
	public static function known_bots(): array {
		return array(
			array(
				'user_agent' => 'GPTBot',
				'label'      => 'OpenAI GPTBot',
				'purpose'    => 'Training data',
			),
			array(
				'user_agent' => 'OAI-SearchBot',
				'label'      => 'OpenAI SearchBot',
				'purpose'    => 'ChatGPT search',
			),
			array(
				'user_agent' => 'ChatGPT-User',
				'label'      => 'ChatGPT User',
				'purpose'    => 'ChatGPT browsing',
			),
			array(
				'user_agent' => 'ClaudeBot',
				'label'      => 'Anthropic ClaudeBot',
				'purpose'    => 'Training + Claude web search',
			),
			array(
				'user_agent' => 'anthropic-ai',
				'label'      => 'Anthropic (legacy)',
				'purpose'    => 'Legacy UA',
			),
			array(
				'user_agent' => 'Google-Extended',
				'label'      => 'Google Extended (Gemini)',
				'purpose'    => 'Gemini training',
			),
			array(
				'user_agent' => 'PerplexityBot',
				'label'      => 'Perplexity',
				'purpose'    => 'Perplexity answers',
			),
			array(
				'user_agent' => 'CCBot',
				'label'      => 'Common Crawl',
				'purpose'    => 'Dataset feeding many LLMs',
			),
			array(
				'user_agent' => 'Applebot-Extended',
				'label'      => 'Apple Intelligence',
				'purpose'    => 'Apple training',
			),
			array(
				'user_agent' => 'Meta-ExternalAgent',
				'label'      => 'Meta AI',
				'purpose'    => 'Meta training',
			),
			array(
				'user_agent' => 'Bytespider',
				'label'      => 'ByteDance Bytespider',
				'purpose'    => 'ByteDance training',
			),
		);
	}

	/**
	 * @param string|bool $public 1 if blog_public=1, 0 otherwise (WP passes as boolean-ish string).
	 */
	public function filter_robots_txt( string $output, $public ): string {
		if ( ! $public ) {
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
