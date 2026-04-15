<?php
namespace GeoBoost\Tests\Unit\LlmsTxt;

use GeoBoost\LlmsTxt\Generator;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase {

    public function test_build_renders_site_header(): void {
        $out = ( new Generator() )->build(
            [
                'site_title'   => 'Jean Galea',
                'tagline'      => 'Entrepreneur & Polymath',
                'description'  => 'Long-form writing on business, tech, and life.',
                'home_url'     => 'https://jeangalea.com',
            ],
            []
        );

        $this->assertStringContainsString( '# Jean Galea', $out );
        $this->assertStringContainsString( '> Entrepreneur & Polymath', $out );
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
}
