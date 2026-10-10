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
}
