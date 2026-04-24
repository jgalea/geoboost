<?php
namespace GeoBoost\Tests\Unit\Analyzer;

use GeoBoost\Analyzer\ContentAnalyzer;
use PHPUnit\Framework\TestCase;

final class ContentAnalyzerTest extends TestCase {

	private array $base = array(
		'title'              => 'Hello',
		'content_html'       => '<p>Body.</p>',
		'excerpt'            => '',
		'author_fields'      => array(),
		'has_featured'       => false,
		'has_article_schema' => false,
		'has_custom_jsonld'  => false,
	);

	public function test_flags_under_150_words_as_not_ok(): void {
		$input                 = $this->base;
		$input['content_html'] = '<p>' . str_repeat( 'word ', 100 ) . '</p>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertFalse( $r['word_count_ok'] );
	}

	public function test_passes_at_150_words(): void {
		$input                 = $this->base;
		$input['content_html'] = '<p>' . str_repeat( 'word ', 160 ) . '</p>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertTrue( $r['word_count_ok'] );
	}

	public function test_long_content_no_longer_penalized(): void {
		$input                 = $this->base;
		$input['content_html'] = '<p>' . str_repeat( 'word ', 5000 ) . '</p>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertTrue( $r['word_count_ok'] );
	}

	public function test_detects_faq_heading_pattern(): void {
		$input                 = $this->base;
		$input['content_html'] = '<h2>How do I install this?</h2><p>Like so.</p>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertTrue( $r['has_faq_signal'] );
	}

	public function test_detects_summary_block_only_via_block_marker(): void {
		$input                 = $this->base;
		$input['content_html'] = '<div class="wp-block-geoboost-summary"><p>x</p></div>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertTrue( $r['has_summary_block'] );
		$this->assertFalse( $r['has_excerpt'] );
	}

	public function test_detects_excerpt_independently_of_summary_block(): void {
		$input            = $this->base;
		$input['excerpt'] = 'A short summary.';
		$r                = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertFalse( $r['has_summary_block'] );
		$this->assertTrue( $r['has_excerpt'] );
	}

	public function test_summary_block_signal_also_accepts_itemprop_abstract(): void {
		$input                 = $this->base;
		$input['content_html'] = '<p itemprop="abstract">x</p>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertTrue( $r['has_summary_block'] );
	}

	public function test_detects_key_facts_block_presence(): void {
		$input                 = $this->base;
		$input['content_html'] = '<dl><dt>x</dt><dd>y</dd></dl>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertTrue( $r['has_key_facts'] );
	}

	public function test_detects_heading_hierarchy_issue(): void {
		$input                 = $this->base;
		$input['content_html'] = '<h2>A</h2><h4>B</h4>';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertFalse( $r['heading_hierarchy_ok'] );
	}

	public function test_detects_outbound_and_internal_links(): void {
		$input                 = $this->base;
		$input['content_html'] = '<a href="https://other.test">x</a> <a href="/internal">y</a>';
		$input['site_host']    = 'site.test';
		$r                     = ( new ContentAnalyzer() )->analyze( $input );
		$this->assertTrue( $r['has_outbound_link'] );
		$this->assertTrue( $r['has_internal_link'] );
	}
}
