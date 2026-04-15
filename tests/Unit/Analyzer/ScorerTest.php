<?php
namespace GeoBoost\Tests\Unit\Analyzer;

use GeoBoost\Analyzer\Scorer;
use PHPUnit\Framework\TestCase;

final class ScorerTest extends TestCase {

	private array $zero = array(
		'word_count_ok'        => false,
		'has_summary'          => false,
		'has_key_facts'        => false,
		'has_faq_signal'       => false,
		'heading_hierarchy_ok' => false,
		'has_featured'         => false,
		'has_article_schema'   => false,
		'has_custom_jsonld'    => false,
		'has_author_eeat'      => false,
		'has_outbound_link'    => false,
		'has_internal_link'    => false,
		'recent'               => false,
	);

	public function test_all_false_scores_zero(): void {
		$this->assertSame( 0, ( new Scorer() )->score( $this->zero ) );
	}

	public function test_all_true_scores_100(): void {
		$all = array_map( static fn( $v ) => true, $this->zero );
		$this->assertSame( 100, ( new Scorer() )->score( $all ) );
	}

	public function test_append_history_caps_at_52(): void {
		$hist = array();
		for ( $i = 0; $i < 60; $i++ ) {
			$hist = ( new Scorer() )->append_history( $hist, 1000 + $i, $i );
		}
		$this->assertCount( 52, $hist );
	}

	public function test_append_history_only_appends_when_changed(): void {
		$h = array( array( 'ts' => 100, 'score' => 50 ) );
		$h = ( new Scorer() )->append_history( $h, 200, 50 );
		$this->assertCount( 1, $h );
		$h = ( new Scorer() )->append_history( $h, 300, 60 );
		$this->assertCount( 2, $h );
	}
}
