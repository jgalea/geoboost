<?php
namespace GeoBoost\Tests\Unit\LlmsTxt;

use GeoBoost\LlmsTxt\OverrideDetector;
use PHPUnit\Framework\TestCase;

final class OverrideDetectorTest extends TestCase {

	public function test_is_overridden_when_header_absent(): void {
		$d = new OverrideDetector();
		$d->set_probe( static fn() => array( 'headers' => array(), 'code' => 200 ) );
		$this->assertTrue( $d->is_overridden( 'https://x.test/llms.txt' ) );
	}

	public function test_not_overridden_when_source_header_matches(): void {
		$d = new OverrideDetector();
		$d->set_probe( static fn() => array(
			'headers' => array( 'x-geoboost-source' => 'geoboost' ),
			'code'    => 200,
		) );
		$this->assertFalse( $d->is_overridden( 'https://x.test/llms.txt' ) );
	}

	public function test_not_overridden_on_404(): void {
		$d = new OverrideDetector();
		$d->set_probe( static fn() => array( 'headers' => array(), 'code' => 404 ) );
		$this->assertFalse( $d->is_overridden( 'https://x.test/llms.txt' ) );
	}

	public function test_not_overridden_on_network_error(): void {
		$d = new OverrideDetector();
		$d->set_probe( static fn() => new \WP_Error( 'http_request_failed' ) );
		$this->assertFalse( $d->is_overridden( 'https://x.test/llms.txt' ) );
	}
}
