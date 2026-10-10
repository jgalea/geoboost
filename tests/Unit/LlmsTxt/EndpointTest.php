<?php
namespace GeoBoost\Tests\Unit\LlmsTxt;

use GeoBoost\LlmsTxt\Cache;
use GeoBoost\LlmsTxt\Endpoint;
use GeoBoost\LlmsTxt\Generator;
use PHPUnit\Framework\TestCase;

final class EndpointTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['geoboost_test_query_vars'] );
	}

	public function test_canonical_redirect_is_skipped_for_llms_txt(): void {
		$GLOBALS['geoboost_test_query_vars'] = array( 'geoboost_llms' => '1' );
		$endpoint                            = new Endpoint( new Generator(), new Cache() );
		$this->assertFalse( $endpoint->skip_canonical_redirect( 'https://example.com/llms.txt/' ) );
	}

	public function test_canonical_redirect_is_kept_elsewhere(): void {
		$endpoint = new Endpoint( new Generator(), new Cache() );
		$this->assertSame( 'https://example.com/about/', $endpoint->skip_canonical_redirect( 'https://example.com/about/' ) );
	}
}
