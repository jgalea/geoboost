<?php
namespace GeoBoost\Admin\Dashboard;

final class Svg {

	/**
	 * Compact horizontal bar chart.
	 *
	 * @param array<string,int> $series
	 */
	public static function bars( array $series, string $label ): string {
		if ( empty( $series ) ) {
			return '<p class="geoboost-empty">' . esc_html__( 'No data yet.', 'geoboost' ) . '</p>';
		}
		$max = max( max( $series ), 1 );
		$out = '<ul class="geoboost-bars" role="list" aria-label="' . esc_attr( $label ) . '">';
		foreach ( $series as $name => $value ) {
			$pct  = (int) round( ( $value / $max ) * 100 );
			$out .= sprintf(
				'<li class="geoboost-bars__row"><span class="geoboost-bars__label">%s</span>' .
				'<span class="geoboost-bars__track"><span class="geoboost-bars__fill" style="width:%d%%"></span></span>' .
				'<span class="geoboost-bars__value">%s</span></li>',
				esc_html( (string) $name ),
				$pct,
				esc_html( number_format_i18n( (int) $value ) )
			);
		}
		return $out . '</ul>';
	}

	/**
	 * Single segmented bar showing score distribution across bins.
	 *
	 * @param array<string,int> $bins
	 */
	public static function segmented( array $bins, int $total ): string {
		if ( $total <= 0 ) {
			return '<p class="geoboost-empty">' . esc_html__( 'No scored posts yet.', 'geoboost' ) . '</p>';
		}
		$labels   = array(
			'poor'      => __( 'Poor', 'geoboost' ),
			'fair'      => __( 'Fair', 'geoboost' ),
			'good'      => __( 'Good', 'geoboost' ),
			'excellent' => __( 'Excellent', 'geoboost' ),
		);
		$segments = '';
		$legend   = '';
		foreach ( $bins as $key => $count ) {
			$pct = $count > 0 ? ( $count / $total ) * 100 : 0;
			if ( $pct > 0 ) {
				$segments .= sprintf(
					'<span class="geoboost-seg__piece geoboost-seg__piece--%s" style="width:%s%%" title="%s: %d"></span>',
					esc_attr( $key ),
					esc_attr( (string) $pct ),
					esc_attr( $labels[ $key ] ),
					(int) $count
				);
			}
			$legend .= sprintf(
				'<li class="geoboost-seg__legend-item"><span class="geoboost-seg__swatch geoboost-seg__swatch--%s"></span>%s <strong>%s</strong></li>',
				esc_attr( $key ),
				esc_html( $labels[ $key ] ),
				esc_html( number_format_i18n( (int) $count ) )
			);
		}
		return '<div class="geoboost-seg">' .
			'<div class="geoboost-seg__bar" role="img" aria-label="' . esc_attr__( 'Score distribution', 'geoboost' ) . '">' . $segments . '</div>' .
			'<ul class="geoboost-seg__legend" role="list">' . $legend . '</ul>' .
			'</div>';
	}

	/**
	 * Smooth sparkline for a 30-day time series.
	 *
	 * @param array<int,array{date:string,value:int}> $points
	 */
	public static function sparkline( array $points, string $label ): string {
		$count = count( $points );
		if ( $count < 2 ) {
			return '';
		}
		$values = array_map(
			static function ( $p ) {
				return (int) $p['value'];
			},
			$points
		);
		$max    = max( max( $values ), 1 );
		$width  = 320;
		$height = 56;
		$pad    = 2;
		$coords = array();
		$area   = array();
		foreach ( $points as $i => $p ) {
			$x = $pad + ( $i / ( $count - 1 ) ) * ( $width - 2 * $pad );
			$y = $height - $pad - ( $p['value'] / $max ) * ( $height - 2 * $pad );
			$x = round( $x, 1 );
			$y = round( $y, 1 );

			$coords[] = $x . ',' . $y;
			$area[]   = $x . ',' . $y;
		}
		array_unshift( $area, $pad . ',' . ( $height - $pad ) );
		$area[] = ( $width - $pad ) . ',' . ( $height - $pad );

		return sprintf(
			'<svg class="geoboost-spark" role="img" aria-label="%s" viewBox="0 0 %d %d" preserveAspectRatio="none">' .
			'<polygon class="geoboost-spark__area" points="%s"/>' .
			'<polyline class="geoboost-spark__line" points="%s"/>' .
			'</svg>',
			esc_attr( $label ),
			(int) $width,
			(int) $height,
			esc_attr( implode( ' ', $area ) ),
			esc_attr( implode( ' ', $coords ) )
		);
	}
}
