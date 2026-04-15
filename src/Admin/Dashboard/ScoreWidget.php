<?php
namespace GeoBoost\Admin\Dashboard;

final class ScoreWidget {

	public function render(): string {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT meta_value AS score, COUNT(*) AS n
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_geoboost_score'
			GROUP BY meta_value"
		);
		$bins = array(
			'Poor (0-40)'        => 0,
			'Fair (40-60)'       => 0,
			'Good (60-80)'       => 0,
			'Excellent (80-100)' => 0,
		);
		foreach ( (array) $rows as $r ) {
			$s = (int) $r->score;
			$n = (int) $r->n;
			if ( $s < 40 ) {
				$bins['Poor (0-40)'] += $n;
			} elseif ( $s < 60 ) {
				$bins['Fair (40-60)'] += $n;
			} elseif ( $s < 80 ) {
				$bins['Good (60-80)'] += $n;
			} else {
				$bins['Excellent (80-100)'] += $n;
			}
		}
		$out  = '<h2>' . esc_html__( 'Score distribution', 'geoboost' ) . '</h2>';
		$out .= SvgChart::bar_chart( $bins );

		$top  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT p.ID, p.post_title, CAST(pm.meta_value AS UNSIGNED) AS s
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_geoboost_score'
			WHERE p.post_status = 'publish' AND p.post_type IN ('post','page')
			ORDER BY s DESC LIMIT 10"
		);
		$out .= '<h3>' . esc_html__( 'Top 10 posts', 'geoboost' ) . '</h3><ol>';
		foreach ( (array) $top as $p ) {
			$out .= sprintf(
				'<li><a href="%s">%s</a> — <strong>%d</strong></li>',
				esc_url( (string) get_edit_post_link( (int) $p->ID ) ),
				esc_html( $p->post_title ),
				(int) $p->s
			);
		}
		$out .= '</ol>';
		return $out;
	}
}
