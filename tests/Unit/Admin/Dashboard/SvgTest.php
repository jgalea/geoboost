<?php
namespace GeoBoost\Tests\Unit\Admin\Dashboard;

use GeoBoost\Admin\Dashboard\Svg;
use PHPUnit\Framework\TestCase;

final class SvgTest extends TestCase {

	public function test_bars_empty_series_returns_empty_state(): void {
		$html = Svg::bars( array(), 'AI bots' );
		$this->assertStringContainsString( 'geoboost-empty', $html );
		$this->assertStringNotContainsString( 'geoboost-bars', $html );
	}

	public function test_bars_renders_label_and_value_for_each_row(): void {
		$html = Svg::bars(
			array(
				'ChatGPT-User' => 500,
				'ClaudeBot'    => 100,
			),
			'AI bots'
		);
		$this->assertStringContainsString( 'ChatGPT-User', $html );
		$this->assertStringContainsString( '500', $html );
		$this->assertStringContainsString( 'ClaudeBot', $html );
		$this->assertStringContainsString( '100', $html );
		$this->assertStringContainsString( 'role="list"', $html );
	}

	public function test_bars_scales_fill_percentage_to_max_value(): void {
		$html = Svg::bars(
			array(
				'A' => 1000,
				'B' => 250,
			),
			'x'
		);
		// Max bar should be 100%, smaller bar 25%.
		$this->assertStringContainsString( 'width:100%', $html );
		$this->assertStringContainsString( 'width:25%', $html );
	}

	public function test_bars_escapes_label_html(): void {
		$html = Svg::bars( array( '<script>bad</script>' => 10 ), 'x' );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_segmented_empty_when_total_zero(): void {
		$html = Svg::segmented(
			array(
				'poor'      => 0,
				'fair'      => 0,
				'good'      => 0,
				'excellent' => 0,
			),
			0
		);
		$this->assertStringContainsString( 'geoboost-empty', $html );
	}

	public function test_segmented_renders_only_non_zero_bins(): void {
		$html = Svg::segmented(
			array(
				'poor'      => 0,
				'fair'      => 50,
				'good'      => 150,
				'excellent' => 0,
			),
			200
		);
		$this->assertStringContainsString( 'geoboost-seg__piece--fair', $html );
		$this->assertStringContainsString( 'geoboost-seg__piece--good', $html );
		$this->assertStringNotContainsString( 'geoboost-seg__piece--poor', $html );
		$this->assertStringNotContainsString( 'geoboost-seg__piece--excellent', $html );
	}

	public function test_segmented_legend_always_lists_all_bins(): void {
		$html = Svg::segmented(
			array(
				'poor'      => 0,
				'fair'      => 50,
				'good'      => 150,
				'excellent' => 0,
			),
			200
		);
		$this->assertStringContainsString( 'geoboost-seg__swatch--poor', $html );
		$this->assertStringContainsString( 'geoboost-seg__swatch--fair', $html );
		$this->assertStringContainsString( 'geoboost-seg__swatch--good', $html );
		$this->assertStringContainsString( 'geoboost-seg__swatch--excellent', $html );
	}

	public function test_sparkline_returns_empty_string_when_fewer_than_two_points(): void {
		$this->assertSame( '', Svg::sparkline( array(), 'x' ) );
		$this->assertSame( '', Svg::sparkline( array( array( 'date' => '2026-01-01', 'value' => 1 ) ), 'x' ) );
	}

	public function test_sparkline_emits_polyline_and_area(): void {
		$points = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$points[] = array(
				'date'  => sprintf( '2026-01-%02d', $i + 1 ),
				'value' => $i * 10,
			);
		}
		$html = Svg::sparkline( $points, 'Daily crawls' );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( 'geoboost-spark__line', $html );
		$this->assertStringContainsString( 'geoboost-spark__area', $html );
		$this->assertStringContainsString( 'Daily crawls', $html );
	}
}
