<?php
namespace GeoBoost\Tests\Unit\Scan;

use GeoBoost\Scan\Checks;
use GeoBoost\Scan\PageParser;
use GeoBoost\Scan\RobotsTxt;
use PHPUnit\Framework\TestCase;

final class ChecksTest extends TestCase {

	private function check( string $category, int $weight, string $status ): array {
		return array(
			'category' => $category,
			'weight'   => $weight,
			'status'   => $status,
		);
	}

	private function input( array $overrides = array() ): array {
		$html = '<html lang="en"><head><link rel="canonical" href="https://example.com/">'
			. '<meta property="og:title" content="T"><meta property="og:description" content="D"><meta property="og:image" content="https://example.com/i.png">'
			. '<script type="application/ld+json">{"@type":"Organization","name":"Acme","sameAs":["https://x.example"],"telephone":"+1"}</script>'
			. '</head><body><h1>Acme</h1><h2>What do we do?</h2><h2>Where are we?</h2><p>' . str_repeat( 'word ', 400 ) . '</p><img src="a.png" alt="A"></body></html>';
		return array_merge(
			array(
				'path'          => '/',
				'headers'       => array(),
				'facts'         => ( new PageParser() )->parse( $html ),
				'robots'        => new RobotsTxt( "User-agent: *\nDisallow: /wp-admin/\n" ),
				'robots_status' => 200,
				'blog_public'   => true,
				'bots'          => array(
					array(
						'name'   => 'GPTBot',
						'status' => 200,
						'error'  => '',
					),
				),
				'sitemap'       => 'https://example.com/wp-sitemap.xml',
				'llms_length'   => 500,
			),
			$overrides
		);
	}

	private function by_id( array $checks ): array {
		return array_column( $checks, null, 'id' );
	}

	public function test_all_pass_scores_100(): void {
		$s = ( new Checks() )->score( array( $this->check( 'access', 5, 'pass' ), $this->check( 'content', 1, 'pass' ) ) );
		$this->assertSame( 100, $s['score'] );
	}

	public function test_all_fail_scores_zero(): void {
		$s = ( new Checks() )->score( array( $this->check( 'access', 5, 'fail' ), $this->check( 'content', 1, 'fail' ) ) );
		$this->assertSame( 0, $s['score'] );
	}

	public function test_warn_counts_half(): void {
		$s = ( new Checks() )->score( array( $this->check( 'access', 2, 'warn' ) ) );
		$this->assertSame( 50, $s['score'] );
	}

	public function test_score_is_weighted(): void {
		$s = ( new Checks() )->score( array( $this->check( 'access', 3, 'pass' ), $this->check( 'access', 1, 'fail' ) ) );
		$this->assertSame( 75, $s['score'] );
	}

	public function test_category_breakdown_and_empty_category_is_null(): void {
		$s = ( new Checks() )->score(
			array(
				$this->check( 'access', 1, 'pass' ),
				$this->check( 'content', 1, 'fail' ),
				$this->check( 'content', 1, 'warn' ),
			)
		);
		$this->assertSame( 100, $s['categories']['access'] );
		$this->assertSame( 25, $s['categories']['content'] );
		$this->assertNull( $s['categories']['discovery'] );
		$this->assertSame( array_keys( Checks::categories() ), array_keys( $s['categories'] ) );
	}

	public function test_no_checks_scores_zero(): void {
		$this->assertSame( 0, ( new Checks() )->score( array() )['score'] );
	}

	public function test_healthy_page_passes_every_check(): void {
		$checks = ( new Checks() )->build( $this->input() );
		$this->assertCount( 16, $checks );
		foreach ( $checks as $c ) {
			$this->assertSame( 'pass', $c['status'], $c['id'] . ': ' . $c['summary'] );
			$this->assertSame( '', $c['fix'] );
		}
		$this->assertSame( 100, ( new Checks() )->score( $checks )['score'] );
	}

	public function test_blocked_search_crawler_fails(): void {
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'robots' => new RobotsTxt( "User-agent: OAI-SearchBot\nDisallow: /\n" ) ) ) ) );
		$this->assertSame( 'fail', $checks['robots-search']['status'] );
		$this->assertStringContainsString( 'OAI-SearchBot', $checks['robots-search']['summary'] );
		$this->assertSame( 'pass', $checks['robots-training']['status'] );
	}

	public function test_blocked_training_crawler_only_warns(): void {
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'robots' => new RobotsTxt( "User-agent: GPTBot\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n" ) ) ) ) );
		$this->assertSame( 'pass', $checks['robots-search']['status'] );
		$this->assertSame( 'warn', $checks['robots-training']['status'] );
		$this->assertStringContainsString( 'GPTBot', $checks['robots-training']['summary'] );
		$this->assertStringContainsString( 'CCBot', $checks['robots-training']['summary'] );
	}

	public function test_blocking_everything_fails_search_and_warns_training(): void {
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'robots' => new RobotsTxt( "User-agent: *\nDisallow: /\n" ) ) ) ) );
		$this->assertSame( 'fail', $checks['robots-search']['status'] );
		$this->assertSame( 'warn', $checks['robots-training']['status'] );
	}

	public function test_missing_robots_file_passes_and_broken_one_warns(): void {
		$missing = $this->by_id( ( new Checks() )->build( $this->input( array( 'robots' => null, 'robots_status' => 404 ) ) ) );
		$this->assertSame( 'pass', $missing['robots']['status'] );
		$this->assertArrayNotHasKey( 'robots-search', $missing );

		$broken = $this->by_id( ( new Checks() )->build( $this->input( array( 'robots' => null, 'robots_status' => 500 ) ) ) );
		$this->assertSame( 'warn', $broken['robots']['status'] );
	}

	public function test_refused_bot_warns_with_status(): void {
		$bots   = array(
			array(
				'name'   => 'ClaudeBot',
				'status' => 403,
				'error'  => '',
			),
		);
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'bots' => $bots ) ) ) );
		$this->assertSame( 'warn', $checks['live-bots']['status'] );
		$this->assertStringContainsString( 'ClaudeBot (HTTP 403)', $checks['live-bots']['summary'] );
		$this->assertNotSame( '', $checks['live-bots']['fix'] );
	}

	public function test_discouraged_site_fails_indexable(): void {
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'blog_public' => false ) ) ) );
		$this->assertSame( 'fail', $checks['indexable']['status'] );
	}

	public function test_x_robots_tag_noindex_fails_indexable(): void {
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'headers' => array( 'x-robots-tag' => 'noindex, nofollow' ) ) ) ) );
		$this->assertSame( 'fail', $checks['indexable']['status'] );
	}

	public function test_noai_meta_warns(): void {
		$facts                = $this->input()['facts'];
		$facts['robots_meta'] = 'index, noai';
		$checks               = $this->by_id( ( new Checks() )->build( $this->input( array( 'facts' => $facts ) ) ) );
		$this->assertSame( 'warn', $checks['indexable']['status'] );
	}

	public function test_app_shell_fails_readable_text(): void {
		$facts  = ( new PageParser() )->parse( '<html><body><div id="root"></div><script>render()</script></body></html>' );
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'facts' => $facts ) ) ) );
		$this->assertSame( 'fail', $checks['readable-text']['status'] );
		$this->assertStringContainsString( 'JavaScript', $checks['readable-text']['summary'] );
		$this->assertSame( 'fail', $checks['h1']['status'] );
		$this->assertSame( 'fail', $checks['org-schema']['status'] );
		$this->assertSame( 'fail', $checks['og']['status'] );
	}

	public function test_missing_sitemap_fails_and_missing_llms_warns(): void {
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'sitemap' => null, 'llms_length' => 0 ) ) ) );
		$this->assertSame( 'fail', $checks['sitemap']['status'] );
		$this->assertSame( 'warn', $checks['llms']['status'] );
	}

	public function test_org_without_same_as_warns(): void {
		$facts            = $this->input()['facts'];
		$facts['json_ld'] = array( array( '@type' => 'LocalBusiness' ) );
		$checks           = $this->by_id( ( new Checks() )->build( $this->input( array( 'facts' => $facts ) ) ) );
		$this->assertSame( 'warn', $checks['org-schema']['status'] );
	}

	public function test_search_and_training_agent_lists(): void {
		$kinds = array_column( Checks::robots_agents(), 'kind', 'token' );
		foreach ( array( 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'Claude-SearchBot', 'Googlebot', 'Bingbot' ) as $t ) {
			$this->assertSame( 'search', $kinds[ $t ], $t );
		}
		foreach ( array( 'GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot' ) as $t ) {
			$this->assertSame( 'training', $kinds[ $t ], $t );
		}
	}

	public function test_unevaluable_robots_warns_instead_of_guessing(): void {
		$robots = new RobotsTxt( "User-agent: OAI-SearchBot\nDisallow: /" . str_repeat( 'x', 600 ) . "\n" );
		$checks = $this->by_id( ( new Checks() )->build( $this->input( array( 'robots' => $robots ) ) ) );
		$this->assertSame( 'warn', $checks['robots']['status'] );
		$this->assertArrayNotHasKey( 'robots-search', $checks );
	}
}
