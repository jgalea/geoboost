<?php
namespace GeoBoost\Tests\Unit\Schema;

use GeoBoost\Schema\Article;
use PHPUnit\Framework\TestCase;

final class ArticleTest extends TestCase {

	private array $input = array(
		'title'         => 'Hello',
		'url'           => 'https://x.test/hello',
		'excerpt'       => 'Summary.',
		'content_html'  => '<p>Body text with <strong>facts</strong>.</p>',
		'date_published'=> '2026-04-01T10:00:00+00:00',
		'date_modified' => '2026-04-05T12:00:00+00:00',
		'author_name'   => 'Jane Doe',
		'author_url'    => 'https://x.test/author/jane/',
		'site_name'     => 'Example',
		'site_url'      => 'https://x.test/',
		'image_url'     => 'https://x.test/img.jpg',
	);

	public function test_build_returns_article_context_and_type(): void {
		$a = ( new Article() )->build( $this->input );
		$this->assertSame( 'https://schema.org', $a['@context'] );
		$this->assertSame( 'Article', $a['@type'] );
	}

	public function test_build_populates_dates_and_headline(): void {
		$a = ( new Article() )->build( $this->input );
		$this->assertSame( 'Hello', $a['headline'] );
		$this->assertSame( '2026-04-01T10:00:00+00:00', $a['datePublished'] );
		$this->assertSame( '2026-04-05T12:00:00+00:00', $a['dateModified'] );
	}

	public function test_build_nests_author_and_publisher(): void {
		$a = ( new Article() )->build( $this->input );
		$this->assertSame( 'Person', $a['author']['@type'] );
		$this->assertSame( 'Jane Doe', $a['author']['name'] );
		$this->assertSame( 'Organization', $a['publisher']['@type'] );
		$this->assertSame( 'Example', $a['publisher']['name'] );
	}

	public function test_build_includes_image_when_provided(): void {
		$a = ( new Article() )->build( $this->input );
		$this->assertSame( 'https://x.test/img.jpg', $a['image'] );
	}

	public function test_build_omits_image_when_missing(): void {
		$input = $this->input;
		unset( $input['image_url'] );
		$a = ( new Article() )->build( $input );
		$this->assertArrayNotHasKey( 'image', $a );
	}
}
