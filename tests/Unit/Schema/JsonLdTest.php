<?php
namespace GeoBoost\Tests\Unit\Schema;

use GeoBoost\Schema\JsonLd;
use PHPUnit\Framework\TestCase;

final class JsonLdTest extends TestCase {

	public function test_render_wraps_in_script_tag(): void {
		$out = JsonLd::render( array( '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'X' ) );
		$this->assertStringStartsWith( '<script type="application/ld+json">', $out );
		$this->assertStringEndsWith( '</script>', $out );
	}

	public function test_render_escapes_closing_script_tag(): void {
		$out = JsonLd::render( array( '@type' => 'Article', 'headline' => '</script>alert(1)' ) );
		$this->assertStringNotContainsString( '</script>alert', $out );
	}

	public function test_render_returns_empty_for_empty_array(): void {
		$this->assertSame( '', JsonLd::render( array() ) );
	}

	public function test_render_strips_null_values(): void {
		$out = JsonLd::render( array( '@type' => 'Article', 'headline' => 'X', 'description' => null ) );
		$this->assertStringNotContainsString( 'description', $out );
	}
}
