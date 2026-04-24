<?php
namespace GeoBoost\Tests\Unit\Analyzer;

use GeoBoost\Analyzer\Recommendations;
use PHPUnit\Framework\TestCase;

final class RecommendationsTest extends TestCase {

    private array $all_passing = array(
        'has_summary_block'    => true,
        'has_excerpt'          => true,
        'has_key_facts'        => true,
        'has_faq_signal'       => true,
        'has_featured'         => true,
        'has_outbound_link'    => true,
        'has_internal_link'    => true,
    );

    public function test_no_recommendations_when_all_passing(): void {
        $r = ( new Recommendations() )->for_signals( $this->all_passing );
        $this->assertEmpty( $r );
    }

    public function test_returns_summary_block_recommendation(): void {
        $signals                      = $this->all_passing;
        $signals['has_summary_block'] = false;
        $r                            = ( new Recommendations() )->for_signals( $signals );
        $this->assertCount( 1, $r );
        $this->assertSame( 'has_summary_block', $r[0]['signal'] );
        $this->assertStringContainsString( 'Summary', $r[0]['text'] );
    }

    public function test_returns_excerpt_recommendation_separately(): void {
        $signals                = $this->all_passing;
        $signals['has_excerpt'] = false;
        $r                      = ( new Recommendations() )->for_signals( $signals );
        $this->assertCount( 1, $r );
        $this->assertSame( 'has_excerpt', $r[0]['signal'] );
    }

    public function test_does_not_return_recommendations_for_essential_signals(): void {
        $signals                         = $this->all_passing;
        $signals['has_article_schema']   = false;
        $signals['heading_hierarchy_ok'] = false;
        $signals['word_count_ok']        = false;
        $signals['has_author_eeat']      = false;
        $r                               = ( new Recommendations() )->for_signals( $signals );
        $this->assertEmpty( $r );
    }
}
