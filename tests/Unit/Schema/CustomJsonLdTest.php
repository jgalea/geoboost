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
}
