<?php
namespace GeoBoost\Tests\Unit\Scan;

use GeoBoost\Scan\SiteScanner;
use PHPUnit\Framework\TestCase;

final class SiteScannerTest extends TestCase {

	public function test_normalize_target_defaults_to_home(): void {
		$this->assertSame( 'https://example.com/', SiteScanner::normalize_target( '', 'https://example.com/' ) );
	}

	public function test_normalize_target_accepts_paths_and_same_host_urls(): void {
		$this->assertSame( 'https://example.com/about/', SiteScanner::normalize_target( '/about/', 'https://example.com/' ) );
		$this->assertSame( 'https://example.com/a/?x=1', SiteScanner::normalize_target( 'https://EXAMPLE.com/a/?x=1#frag', 'https://example.com/' ) );
		$this->assertSame( 'http://localhost:8888/p/', SiteScanner::normalize_target( '/p/', 'http://localhost:8888/' ) );
	}

	public function test_normalize_target_rejects_other_hosts(): void {
		$home = 'https://example.com/';
		$this->assertNull( SiteScanner::normalize_target( 'https://evil.example/', $home ) );
		$this->assertNull( SiteScanner::normalize_target( '//evil.example/', $home ) );
		$this->assertNull( SiteScanner::normalize_target( 'https://example.com.evil.example/', $home ) );
		$this->assertNull( SiteScanner::normalize_target( 'https://example.com:8080/', $home ) );
		$this->assertNull( SiteScanner::normalize_target( 'https://user:pw@example.com/', $home ) );
		$this->assertNull( SiteScanner::normalize_target( 'ftp://example.com/', $home ) );
	}

	public function test_scan_reports_unreachable_page(): void {
		$s = new SiteScanner();
		$s->set_fetcher(
			static function (): array {
				return array( 'error' => 'cURL error 7' );
			}
		);
		$r = $s->scan( 'https://example.com/', true );
		$this->assertStringContainsString( 'cURL error 7', $r['error'] );
		$this->assertArrayNotHasKey( 'checks', $r );
	}

	public function test_scan_end_to_end_with_fake_site(): void {
		$requests = array();
		$s        = new SiteScanner();
		$s->set_fetcher(
			static function ( string $url, string $ua ) use ( &$requests ): array {
				$requests[] = $url . ' ' . $ua;
				switch ( $url ) {
					case 'https://example.com/':
						if ( false !== strpos( $ua, 'ClaudeBot' ) ) {
							return array( 'status' => 403 );
						}
						return array(
							'status' => 200,
							'body'   => '<html lang="en"><body><h1>Hi</h1><p>' . str_repeat( 'text ', 320 ) . '</p></body></html>',
						);
					case 'https://example.com/robots.txt':
						return array(
							'status' => 200,
							'body'   => "User-agent: PerplexityBot\nDisallow: /\nSitemap: https://cdn.other.example/s.xml\nSitemap: https://example.com/custom.xml\n",
						);
					case 'https://example.com/llms.txt':
						return array(
							'status' => 200,
							'body'   => '<!DOCTYPE html><html><body>Not found</body></html>',
						);
					case 'https://example.com/custom.xml':
						return array(
							'status' => 200,
							'body'   => '<?xml version="1.0"?><urlset></urlset>',
						);
				}
				return array( 'status' => 404 );
			}
		);

		$r      = $s->scan( 'https://example.com/', true );
		$checks = array_column( $r['checks'], null, 'id' );

		$this->assertSame( 'fail', $checks['robots-search']['status'] );
		$this->assertSame( 'warn', $checks['live-bots']['status'] );
		$this->assertStringContainsString( 'ClaudeBot (HTTP 403)', $checks['live-bots']['summary'] );
		$this->assertSame( 'warn', $checks['llms']['status'] );
		$this->assertSame( 'pass', $checks['sitemap']['status'] );
		$this->assertStringContainsString( 'https://example.com/custom.xml', $checks['sitemap']['summary'] );
		$this->assertSame( 'pass', $checks['readable-text']['status'] );
		$this->assertGreaterThan( 0, $r['score'] );
		$this->assertLessThan( 100, $r['score'] );

		$fetched = implode( "\n", $requests );
		$this->assertStringNotContainsString( 'cdn.other.example', $fetched );
		$this->assertStringContainsString( 'GPTBot', $fetched );
		$this->assertStringContainsString( 'PerplexityBot/1.0', $fetched );
	}

	private function scan_with_bot_response( array $bot, string $page_body = '' ): array {
		$page_body = '' !== $page_body ? $page_body : '<html lang="en"><body><h1>Hi</h1><p>' . str_repeat( 'text ', 320 ) . '</p></body></html>';
		$s         = new SiteScanner();
		$s->set_fetcher(
			static function ( string $url, string $ua ) use ( $bot, $page_body ): array {
				if ( 'https://example.com/' !== $url ) {
					return array( 'status' => 404 );
				}
				if ( false !== strpos( $ua, 'GPTBot' ) ) {
					return $bot;
				}
				return array(
					'status' => 200,
					'body'   => $page_body,
				);
			}
		);
		$checks = array_column( $s->scan( 'https://example.com/', true )['checks'], null, 'id' );
		return $checks['live-bots'];
	}

	public function test_identical_bot_page_passes(): void {
		$page = '<html><body><p>' . str_repeat( 'text ', 320 ) . '</p></body></html>';
		$this->assertSame(
			'pass',
			$this->scan_with_bot_response(
				array(
					'status' => 200,
					'body'   => $page,
				),
				$page
			)['status']
		);
	}

	public function test_challenge_page_with_200_counts_as_blocked(): void {
		$check = $this->scan_with_bot_response(
			array(
				'status' => 200,
				'body'   => '<html><head><title>Just a moment...</title></head><body>' . str_repeat( 'text ', 320 ) . '</body></html>',
			)
		);
		$this->assertSame( 'warn', $check['status'] );
		$this->assertStringContainsString( 'GPTBot', $check['summary'] );
	}

	public function test_thin_bot_page_counts_as_blocked(): void {
		$check = $this->scan_with_bot_response(
			array(
				'status' => 200,
				'body'   => '<html><body><p>Please wait.</p></body></html>',
			)
		);
		$this->assertSame( 'warn', $check['status'] );
	}

	public function test_bot_redirected_to_another_host_or_login_counts_as_blocked(): void {
		foreach ( array( 'https://shield.example/check', 'https://example.com/wp-login.php?redirect_to=%2F' ) as $final ) {
			$check = $this->scan_with_bot_response(
				array(
					'status'    => 200,
					'body'      => '<html><body><p>' . str_repeat( 'text ', 320 ) . '</p></body></html>',
					'final_url' => $final,
				)
			);
			$this->assertSame( 'warn', $check['status'], $final );
		}
	}

	public function test_bot_copy_is_fetched_with_the_page_size_cap(): void {
		$caps = array();
		$s    = new SiteScanner();
		$s->set_fetcher(
			static function ( string $url, string $ua, int $cap = 0 ) use ( &$caps ): array {
				if ( 'https://example.com/' === $url ) {
					$caps[ false !== strpos( $ua, 'GPTBot' ) ? 'bot' : ( false !== strpos( $ua, 'GEOBoost' ) ? 'page' : 'other' ) ] = $cap;
					return array(
						'status' => 200,
						'body'   => '<html><body>hi</body></html>',
					);
				}
				return array( 'status' => 404 );
			}
		);
		$s->scan( 'https://example.com/', true );
		$this->assertGreaterThan( 0, $caps['page'] );
		$this->assertSame( $caps['page'], $caps['bot'] );
	}

	public function test_robots_rules_match_the_query_string(): void {
		$s = new SiteScanner();
		$s->set_fetcher(
			static function ( string $url ): array {
				if ( 'https://example.com/?lang=en' === $url ) {
					return array(
						'status' => 200,
						'body'   => '<html><body>hi</body></html>',
					);
				}
				if ( 'https://example.com/robots.txt' === $url ) {
					return array(
						'status' => 200,
						'body'   => "User-agent: OAI-SearchBot\nDisallow: /*?lang=\n",
					);
				}
				return array( 'status' => 404 );
			}
		);
		$checks = array_column( $s->scan( 'https://example.com/?lang=en', true )['checks'], null, 'id' );
		$this->assertSame( 'fail', $checks['robots-search']['status'] );
	}
}
