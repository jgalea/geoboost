<?php
namespace GeoBoost\Tests\Unit\Scan;

use GeoBoost\Scan\PageParser;
use PHPUnit\Framework\TestCase;

final class PageParserTest extends TestCase {

	public function test_extracts_core_facts(): void {
		$html = '<!doctype html><html lang="pt-PT"><head>'
			. '<meta name="robots" content="NoIndex"><meta property="og:title" content="Hi &amp; bye">'
			. '<link rel="alternate canonical" href="https://example.com/x/">'
			. '<script type="application/ld+json">{"@graph":[{"@type":["WebSite"]},{"@type":"https://schema.org/Dentist","sameAs":"https://y.example"}]}</script>'
			. '<script type="application/ld+json">{broken</script>'
			. '<script>var h1 = "<h1>not a heading</h1>";</script>'
			. '</head><body><!-- <h2>ghost</h2> --><h1>Main <em>title</em></h1><h2>Q one?</h2><h3>Q two?</h3>'
			. '<p>Café opens at 9 &mdash; 5.</p><img src="a" alt=""><img src="b">'
			. '<a href="tel:+351">call</a><a href=\'mailto:a@example.com\'>mail</a></body></html>';

		$f = ( new PageParser() )->parse( $html );

		$this->assertSame( 'pt-PT', $f['lang'] );
		$this->assertSame( 'noindex', $f['robots_meta'] );
		$this->assertSame( 'Hi & bye', $f['og']['og:title'] );
		$this->assertSame( 'https://example.com/x/', $f['canonical'] );
		$this->assertSame( array( 'Main title' ), $f['h1'] );
		$this->assertSame( array( 'Q one?' ), $f['h2'] );
		$this->assertSame( array( 'Main title', 'Q one?', 'Q two?' ), $f['headings'] );
		$this->assertSame( 2, $f['images'] );
		$this->assertSame( 1, $f['images_alt'] );
		$this->assertSame( 1, $f['json_ld_errors'] );
		$this->assertEqualsCanonicalizing( array( 'WebSite', 'Dentist' ), $f['schema_types'] );
		$this->assertSame( 1, $f['tel_links'] );
		$this->assertSame( 1, $f['mailto_links'] );
		$this->assertFalse( $f['app_shell'] );
		// Main, title, Q, one?, Q, two?, Café, opens, at, 9, 5., call, mail.
		$this->assertSame( 13, $f['word_count'] );
	}

	public function test_finds_nested_org_node(): void {
		$p   = new PageParser();
		$f   = $p->parse( '<script type="application/ld+json">{"@type":"WebPage","publisher":{"@type":"Organization","name":"Acme"}}</script>' );
		$org = $p->find_org( $f['json_ld'] );
		$this->assertSame( 'Acme', $org['name'] ?? null );
	}

	public function test_invalid_utf8_still_counts_words(): void {
		$f = ( new PageParser() )->parse( "<html><body><p>caf\xE9 ol\xE9 one two</p></body></html>" );
		$this->assertSame( 4, $f['word_count'] );
	}

	public function test_detects_app_shell(): void {
		$f = ( new PageParser() )->parse( '<html><body><div id="__next"></div></body></html>' );
		$this->assertTrue( $f['app_shell'] );
		$this->assertSame( 0, $f['word_count'] );
	}
}
