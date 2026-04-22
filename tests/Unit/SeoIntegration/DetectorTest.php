<?php
namespace GeoBoost\Tests\Unit\SeoIntegration;

use GeoBoost\SeoIntegration\Detector;
use PHPUnit\Framework\TestCase;

final class DetectorTest extends TestCase {

	public function test_active_returns_empty_when_no_seo_plugin_classes(): void {
		$d = new Detector( array() );
		$this->assertSame( array(), $d->active() );
	}

	public function test_active_returns_yoast(): void {
		$d = new Detector( array( 'yoast' => true ) );
		$this->assertContains( 'yoast', $d->active() );
	}

	public function test_active_returns_tsf(): void {
		$d = new Detector( array( 'tsf' => true ) );
		$this->assertContains( 'tsf', $d->active() );
	}

	public function test_emits_type_parses_jsonld_blocks(): void {
		$d    = new Detector( array() );
		$html = '<head><script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"X"}</script></head>';
		$this->assertTrue( $d->html_emits_type( $html, 'Article' ) );
		$this->assertFalse( $d->html_emits_type( $html, 'FAQPage' ) );
	}

	public function test_emits_type_handles_graph(): void {
		$d    = new Detector( array() );
		$html = '<script type="application/ld+json">{"@graph":[{"@type":"BlogPosting"},{"@type":"Person"}]}</script>';
		$this->assertTrue( $d->html_emits_type( $html, 'BlogPosting' ) );
		$this->assertTrue( $d->html_emits_type( $html, 'Person' ) );
		$this->assertFalse( $d->html_emits_type( $html, 'FAQPage' ) );
	}
}
