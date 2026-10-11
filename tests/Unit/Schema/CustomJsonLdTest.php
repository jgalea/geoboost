<?php
namespace GeoBoost\Tests\Unit\Schema;

use GeoBoost\Schema\CustomJsonLd;
use PHPUnit\Framework\TestCase;

final class CustomJsonLdTest extends TestCase {

	public function test_empty_string_is_valid(): void {
		$this->assertTrue( ( new CustomJsonLd() )->validate( '' )['ok'] );
	}

	public function test_invalid_json_rejected(): void {
		$r = ( new CustomJsonLd() )->validate( '{not-json' );
		$this->assertFalse( $r['ok'] );
		$this->assertStringContainsString( 'JSON', $r['error'] );
	}

	public function test_missing_context_and_type_rejected(): void {
		$r = ( new CustomJsonLd() )->validate( '{"headline":"X"}' );
		$this->assertFalse( $r['ok'] );
	}

	public function test_valid_jsonld_accepted(): void {
		$r = ( new CustomJsonLd() )->validate( '{"@context":"https://schema.org","@type":"Article","headline":"X"}' );
		$this->assertTrue( $r['ok'] );
	}

	public function test_normalize_neutralises_script_breakouts(): void {
		$out = ( new CustomJsonLd() )->normalize( '{"@context":"https://schema.org","@type":"Article","headline":"<!-- x </script><script>alert(1)</script>"}' );
		$this->assertStringNotContainsString( '<', $out );
		$this->assertStringNotContainsString( '>', $out );
		$this->assertStringContainsString( 'https://schema.org', $out );
		$this->assertSame( '<!-- x </script><script>alert(1)</script>', json_decode( $out, true )['headline'] );
	}

	public function test_normalize_keeps_empty_objects_and_unicode(): void {
		$this->assertSame( '{"@type":"Thing","name":"Café","extra":{}}', ( new CustomJsonLd() )->normalize( ' {"@type": "Thing", "name": "Café", "extra": {}} ' ) );
	}

	public function test_normalize_rejects_non_json_and_scalars(): void {
		$n = new CustomJsonLd();
		$this->assertSame( '', $n->normalize( '' ) );
		$this->assertSame( '', $n->normalize( '{not-json' ) );
		$this->assertSame( '', $n->normalize( '"just a string"' ) );
	}
}
