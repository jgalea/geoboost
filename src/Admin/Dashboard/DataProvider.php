<?php
namespace GeoBoost\Admin\Dashboard;

use GeoBoost\Admin\Dismissals;
use GeoBoost\Events\EventsTable;
use GeoBoost\LlmsTxt\OverrideDetector;
use GeoBoost\SeoIntegration\Detector;

final class DataProvider {

	private const CACHE_KEY = 'geoboost_dashboard_snapshot';
	private const CACHE_TTL = 60;
	private const WINDOW    = 30;

	public function get(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$data = array(
			'scores'          => $this->scores(),
			'bots'            => $this->events( 'bot_crawl' ),
			'referrals'       => $this->events( 'ai_referral' ),
			'sparkline'       => $this->sparkline( 'bot_crawl' ),
			'top_posts'       => $this->top_posts(),
			'recommendations' => $this->recommendations(),
			'health'          => $this->health(),
			'generated_at'    => time(),
		);
		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	private function scores(): array {
		global $wpdb;
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT CAST(meta_value AS UNSIGNED) AS score, COUNT(*) AS n
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_geoboost_score'
			GROUP BY meta_value"
		);
		$bins  = array(
			'poor'      => 0,
			'fair'      => 0,
			'good'      => 0,
			'excellent' => 0,
		);
		$total = 0;
		$sum   = 0;
		foreach ( (array) $rows as $r ) {
			$score  = (int) $r->score;
			$count  = (int) $r->n;
			$total += $count;
			$sum   += $score * $count;
			if ( $score < 40 ) {
				$bins['poor'] += $count;
			} elseif ( $score < 60 ) {
				$bins['fair'] += $count;
			} elseif ( $score < 80 ) {
				$bins['good'] += $count;
			} else {
				$bins['excellent'] += $count;
			}
		}
		return array(
			'bins'    => $bins,
			'total'   => $total,
			'average' => $total > 0 ? (int) round( $sum / $total ) : 0,
		);
	}

	private function events( string $type ): array {
		global $wpdb;
		$now          = time();
		$current_from = gmdate( 'Y-m-d H:i:s', $now - ( self::WINDOW * DAY_IN_SECONDS ) );
		$prior_from   = gmdate( 'Y-m-d H:i:s', $now - ( 2 * self::WINDOW * DAY_IN_SECONDS ) );
		$prior_to     = $current_from;
		$table        = EventsTable::name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_or_source, COUNT(*) AS n FROM {$table}
				WHERE event_type = %s AND created_at >= %s
				GROUP BY bot_or_source ORDER BY n DESC",
				$type,
				$current_from
			)
		);
		$current_total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND created_at >= %s",
				$type,
				$current_from
			)
		);
		$prior_total   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND created_at >= %s AND created_at < %s",
				$type,
				$prior_from,
				$prior_to
			)
		);
		// phpcs:enable

		$series = array();
		foreach ( (array) $rows as $r ) {
			$series[ (string) $r->bot_or_source ] = (int) $r->n;
		}
		return array(
			'series'  => $series,
			'total'   => $current_total,
			'prior'   => $prior_total,
			'delta'   => $current_total - $prior_total,
			'percent' => $prior_total > 0 ? (int) round( ( ( $current_total - $prior_total ) / $prior_total ) * 100 ) : null,
		);
	}

	private function sparkline( string $type ): array {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d', time() - ( self::WINDOW * DAY_IN_SECONDS ) );
		$table  = EventsTable::name();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS n FROM {$table}
				WHERE event_type = %s AND created_at >= %s
				GROUP BY DATE(created_at) ORDER BY d ASC",
				$type,
				$cutoff
			)
		);
		// phpcs:enable
		$by_day = array();
		foreach ( (array) $rows as $r ) {
			$by_day[ (string) $r->d ] = (int) $r->n;
		}
		$points = array();
		for ( $i = self::WINDOW - 1; $i >= 0; $i-- ) {
			$day      = gmdate( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) );
			$points[] = array(
				'date'  => $day,
				'value' => $by_day[ $day ] ?? 0,
			);
		}
		return $points;
	}

	private function top_posts(): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT p.ID, p.post_title, CAST(pm.meta_value AS UNSIGNED) AS score, hm.meta_value AS history
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_geoboost_score'
			LEFT JOIN {$wpdb->postmeta} hm ON hm.post_id = p.ID AND hm.meta_key = '_geoboost_score_history'
			WHERE p.post_status = 'publish' AND p.post_type IN ('post','page')
			ORDER BY score DESC LIMIT 10"
		);
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$scanned = 0;
			if ( ! empty( $r->history ) ) {
				$hist = maybe_unserialize( $r->history );
				if ( is_array( $hist ) && ! empty( $hist ) ) {
					$last    = end( $hist );
					$scanned = is_array( $last ) ? (int) ( $last['ts'] ?? 0 ) : 0;
				}
			}
			$out[] = array(
				'id'      => (int) $r->ID,
				'title'   => (string) $r->post_title,
				'score'   => (int) $r->score,
				'edit'    => (string) get_edit_post_link( (int) $r->ID ),
				'scanned' => $scanned,
			);
		}
		return $out;
	}

	private function health(): array {
		$detector = new Detector();
		$seo      = $detector->active();
		$bots     = (array) get_option( 'geoboost_blocked_ai_bots', array() );
		$override = ( new OverrideDetector() )->check_cached( home_url( '/llms.txt' ) );
		return array(
			'seo_plugins'  => $seo,
			'bots_blocked' => count( array_filter( array_map( 'strval', $bots ) ) ),
			'llms_ok'      => ! $override,
		);
	}

	private function recommendations(): array {
		global $wpdb;
		$rows          = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT post_id, meta_value AS details FROM {$wpdb->postmeta} WHERE meta_key = '_geoboost_score_details'"
		);
		$counts        = array(
			'missing_summary'     => 0,
			'missing_key_facts'   => 0,
			'missing_faq'         => 0,
			'heading_hierarchy'   => 0,
			'word_count'          => 0,
			'missing_author_eeat' => 0,
			'missing_featured'    => 0,
		);
		$signal_map    = array(
			'missing_summary'     => 'has_summary',
			'missing_key_facts'   => 'has_key_facts',
			'missing_faq'         => 'has_faq_signal',
			'heading_hierarchy'   => 'heading_hierarchy_ok',
			'word_count'          => 'word_count_ok',
			'missing_author_eeat' => 'has_author_eeat',
			'missing_featured'    => 'has_featured',
		);
		$dismissals    = new Dismissals();
		$dismissed_map = array();
		foreach ( array_keys( $signal_map ) as $type ) {
			$dismissed_map[ $type ] = array_flip( $dismissals->dismissed_ids_for_type( $type ) );
		}
		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row->post_id;
			$d       = maybe_unserialize( $row->details );
			if ( ! is_array( $d ) ) {
				continue;
			}
			foreach ( $signal_map as $type => $signal_key ) {
				if ( empty( $d[ $signal_key ] ) && ! isset( $dismissed_map[ $type ][ $post_id ] ) ) {
					++$counts[ $type ];
				}
			}
		}
		return $counts;
	}
}
