<?php
namespace GeoBoost\Tests\Unit\Analyzer;

use GeoBoost\Analyzer\SignalCategories;
use PHPUnit\Framework\TestCase;

final class SignalCategoriesTest extends TestCase {

    public function test_essentials_contains_expected_keys(): void {
        $essentials = SignalCategories::essentials();
        $this->assertContains( 'has_article_schema', $essentials );
        $this->assertContains( 'heading_hierarchy_ok', $essentials );
        $this->assertContains( 'word_count_ok', $essentials );
        $this->assertContains( 'has_author_eeat', $essentials );
        $this->assertCount( 4, $essentials );
    }

    public function test_recommended_contains_expected_keys(): void {
        $recommended = SignalCategories::recommended();
        $this->assertContains( 'has_summary_block', $recommended );
        $this->assertContains( 'has_excerpt', $recommended );
        $this->assertContains( 'has_key_facts', $recommended );
        $this->assertContains( 'has_faq_signal', $recommended );
        $this->assertContains( 'has_featured', $recommended );
        $this->assertContains( 'has_outbound_link', $recommended );
        $this->assertContains( 'has_internal_link', $recommended );
        $this->assertCount( 7, $recommended );
    }

    public function test_essentials_and_recommended_do_not_overlap(): void {
        $overlap = array_intersect( SignalCategories::essentials(), SignalCategories::recommended() );
        $this->assertEmpty( $overlap );
    }

    public function test_category_for_returns_correct_bucket(): void {
        $this->assertSame( 'essential', SignalCategories::category_for( 'has_article_schema' ) );
        $this->assertSame( 'recommended', SignalCategories::category_for( 'has_summary_block' ) );
        $this->assertSame( null, SignalCategories::category_for( 'unknown_signal' ) );
    }
}
