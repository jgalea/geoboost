<?php
namespace GeoBoost\Tests\Unit\LlmsTxt;

use GeoBoost\LlmsTxt\Generator;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase {

    public function test_build_renders_site_header(): void {
        $out = ( new Generator() )->build(
            [
                'site_title'   => 'Example Site',
                'tagline'      => 'A tagline for the site',
                'description'  => 'Long-form writing on business, tech, and life.',
                'home_url'     => 'https://example.com',
            ],
            []
        );

        $this->assertStringContainsString( '# Example Site', $out );
        $this->assertStringContainsString( '> A tagline for the site', $out );
        $this->assertStringContainsString( 'Long-form writing on business, tech, and life.', $out );
    }

    public function test_build_includes_posts_section(): void {
        $out = ( new Generator() )->build(
            [ 'site_title' => 'S', 'tagline' => 'T', 'description' => 'D', 'home_url' => 'https://x.test' ],
            [
                [
                    'title'   => 'Hello World',
                    'url'     => 'https://x.test/hello',
                    'excerpt' => 'First post.',
                ],
            ]
        );

        $this->assertStringContainsString( '## Posts', $out );
        $this->assertStringContainsString( '[Hello World](https://x.test/hello)', $out );
        $this->assertStringContainsString( 'First post.', $out );
    }

    public function test_build_omits_posts_section_when_empty(): void {
        $out = ( new Generator() )->build(
            [ 'site_title' => 'S', 'tagline' => '', 'description' => '', 'home_url' => 'https://x.test' ],
            []
        );

        $this->assertStringNotContainsString( '## Posts', $out );
    }

    public function test_build_strips_newlines_from_excerpt(): void {
        $out = ( new Generator() )->build(
            [ 'site_title' => 'S', 'tagline' => '', 'description' => '', 'home_url' => 'https://x.test' ],
            [ [ 'title' => 'T', 'url' => 'https://x.test/t', 'excerpt' => "line1\nline2" ] ]
        );

        $this->assertStringNotContainsString( "line1\nline2", $out );
        $this->assertStringContainsString( 'line1 line2', $out );
    }

    public function test_build_renders_key_pages_section(): void {
        $out = ( new Generator() )->build(
            array( 'site_title' => 'S', 'tagline' => '', 'description' => '', 'home_url' => 'https://x.test' ),
            array(),
            array(
                'key_pages' => array(
                    array( 'title' => 'Guide', 'url' => 'https://x.test/guide', 'summary' => 'A guide.' ),
                ),
            )
        );
        $this->assertStringContainsString( '## Key Pages', $out );
        $this->assertStringContainsString( '[Guide](https://x.test/guide)', $out );
    }

    public function test_build_renders_authors_section(): void {
        $out = ( new Generator() )->build(
            array( 'site_title' => 'S', 'tagline' => '', 'description' => '', 'home_url' => 'https://x.test' ),
            array(),
            array(
                'authors' => array(
                    array( 'name' => 'Jane', 'url' => 'https://x.test/a/jane', 'bio' => 'Editor' ),
                ),
            )
        );
        $this->assertStringContainsString( '## Authors', $out );
        $this->assertStringContainsString( '[Jane]', $out );
    }
}
