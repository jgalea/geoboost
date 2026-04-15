<?php
namespace GeoBoost\Admin\Dashboard;

use GeoBoost\Events\EventsTable;

final class ReferralWidget {

	public function render(): string {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'SELECT bot_or_source, COUNT(*) AS n FROM ' . EventsTable::name() .
			' WHERE event_type = %s AND created_at >= %s GROUP BY bot_or_source ORDER BY n DESC',
			'ai_referral',
			$cutoff
		) );
		$series = array();
		foreach ( (array) $rows as $r ) {
			$series[ (string) $r->bot_or_source ] = (int) $r->n;
		}
		return '<h2>' . esc_html__( 'AI referral clicks (last 30 days)', 'geoboost' ) . '</h2>' . SvgChart::bar_chart( $series );
	}
}
