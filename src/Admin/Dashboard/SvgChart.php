<?php
namespace GeoBoost\Admin\Dashboard;

final class SvgChart {

	/**
	 * @param array<string,int> $series label => value
	 */
	public static function bar_chart( array $series, int $width = 600, int $height = 240 ): string {
		if ( empty( $series ) ) {
			return '<p>' . esc_html__( 'No data yet.', 'geoboost' ) . '</p>';
		}
		$max    = max( max( $series ), 1 );
		$count  = count( $series );
		$pad_l  = 140;
		$pad_r  = 20;
		$bar_h  = 22;
		$gap    = 4;
		$needed = $count * ( $bar_h + $gap );
		$height = max( $height, $needed + 20 );

		$out    = '<svg role="img" aria-label="' . esc_attr__( 'Bar chart', 'geoboost' ) . '" width="' . (int) $width . '" height="' . (int) $height . '" style="max-width:100%">';
		$i      = 0;
		$usable = $width - $pad_l - $pad_r;
		foreach ( $series as $label => $value ) {
			$w    = (int) round( ( $value / $max ) * $usable );
			$y    = 10 + $i * ( $bar_h + $gap );
			$ly   = $y + $bar_h - 6;
			$out .= sprintf(
				'<text x="10" y="%d" font-size="12" fill="#1d2327">%s</text>' .
				'<rect x="%d" y="%d" width="%d" height="%d" fill="#2271b1" rx="3"></rect>' .
				'<text x="%d" y="%d" font-size="12" fill="#50575e">%d</text>',
				$ly,
				esc_html( (string) $label ),
				$pad_l,
				$y,
				max( 1, $w ),
				$bar_h,
				$pad_l + $w + 6,
				$ly,
				(int) $value
			);
			++$i;
		}
		return $out . '</svg>';
	}

	/**
	 * @param array<int,array{ts:int,score:int}> $points
	 */
	public static function line_chart( array $points, int $width = 600, int $height = 200 ): string {
		if ( count( $points ) < 2 ) {
			return '<p>' . esc_html__( 'Not enough data yet.', 'geoboost' ) . '</p>';
		}
		usort( $points, static fn( $a, $b ) => $a['ts'] <=> $b['ts'] );
		$min_ts = $points[0]['ts'];
		$max_ts = end( $points )['ts'];
		$span   = max( 1, $max_ts - $min_ts );
		$pad    = 20;
		$coords = array();
		foreach ( $points as $p ) {
			$x        = $pad + ( ( $p['ts'] - $min_ts ) / $span ) * ( $width - 2 * $pad );
			$y        = $height - $pad - ( $p['score'] / 100 ) * ( $height - 2 * $pad );
			$coords[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}
		$poly = implode( ' ', $coords );
		return sprintf(
			'<svg role="img" aria-label="%s" width="%d" height="%d" style="max-width:100%%"><polyline fill="none" stroke="#2271b1" stroke-width="2" points="%s"/></svg>',
			esc_attr__( 'Score trend', 'geoboost' ),
			$width,
			$height,
			esc_attr( $poly )
		);
	}
}
