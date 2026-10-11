<?php
namespace GeoBoost\Tests\Unit\Author;

use GeoBoost\Author\ProfileFields;
use PHPUnit\Framework\TestCase;

final class ProfileFieldsTest extends TestCase {

	public function test_sanitize_expertise_splits_comma_list(): void {
		$p = new ProfileFields();
		$this->assertSame( array( 'SEO', 'Content Marketing', 'Email' ), $p->sanitize_expertise( 'SEO, Content Marketing , Email' ) );
	}

	public function test_sanitize_expertise_dedupes(): void {
		$p = new ProfileFields();
		$this->assertSame( array( 'SEO', 'Content' ), $p->sanitize_expertise( 'SEO, content, SEO, Content' ) );
	}

	public function test_sanitize_expertise_strips_markup(): void {
		$p = new ProfileFields();
		$this->assertSame( array( 'SEO', 'Content' ), $p->sanitize_expertise( '<b>SEO</b>, <script>alert(1)</script>Content' ) );
	}

	public function test_sanitize_urls_keeps_only_http(): void {
		$p    = new ProfileFields();
		$urls = $p->sanitize_urls( array( 'https://x.test', 'javascript:alert(1)', 'http://y.test', 'not a url' ) );
		$this->assertSame( array( 'https://x.test', 'http://y.test' ), $urls );
	}
}
