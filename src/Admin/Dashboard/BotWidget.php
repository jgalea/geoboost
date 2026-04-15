<?php
namespace GeoBoost\Admin\Dashboard;

use GeoBoost\Events\EventsTable;

final class BotWidget {

	public function render(): string {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT bot_or_source, COUNT(*) AS n FROM ' . EventsTable::name() .
				' WHERE event_type = %s AND created_at >= %s GROUP BY bot_or_source ORDER BY n DESC',
				'bot_crawl',
				$cutoff
			)
		);
		// phpcs:enable
		$series = array();
		foreach ( (array) $rows as $r ) {
			$series[ (string) $r->bot_or_source ] = (int) $r->n;
		}
		return '<h2>' . esc_html__( 'AI bot activity (last 30 days)', 'geoboost' ) . '</h2>' . SvgChart::bar_chart( $series );
	}
}
