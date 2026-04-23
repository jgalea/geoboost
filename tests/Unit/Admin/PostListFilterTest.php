<?php
namespace GeoBoost\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PostListFilterTest extends TestCase {

	public function test_signals_and_labels_cover_same_keys(): void {
		$ref     = new ReflectionClass( \GeoBoost\Admin\PostListFilter::class );
		$signals = $ref->getConstant( 'SIGNALS' );
		$labels  = $ref->getConstant( 'LABELS' );
		$this->assertIsArray( $signals );
		$this->assertIsArray( $labels );
		$this->assertSame( array_keys( $signals ), array_keys( $labels ) );
	}

	public function test_every_signal_maps_to_a_snake_case_detail_key(): void {
		$ref     = new ReflectionClass( \GeoBoost\Admin\PostListFilter::class );
		$signals = $ref->getConstant( 'SIGNALS' );
		foreach ( $signals as $filter_key => $detail_key ) {
			$this->assertIsString( $filter_key );
			$this->assertIsString( $detail_key );
			$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]*$/', $detail_key );
		}
	}

	public function test_signal_keys_match_scorer_input_shape(): void {
		// Filter keys map to detail keys that Scorer also inspects via signals array.
		// This guards against renaming signals in one place but not the other.
		$expected_detail_keys = array(
			'has_summary',
			'has_key_facts',
			'has_faq_signal',
			'heading_hierarchy_ok',
			'word_count_ok',
			'has_author_eeat',
			'has_featured',
		);
		$ref     = new ReflectionClass( \GeoBoost\Admin\PostListFilter::class );
		$signals = $ref->getConstant( 'SIGNALS' );
		$actual  = array_values( $signals );
		sort( $actual );
		sort( $expected_detail_keys );
		$this->assertSame( $expected_detail_keys, $actual );
	}
}
