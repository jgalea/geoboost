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
		$issues = $this->issues();
		$data   = array(
			'essentials'               => $issues['essentials'],
			'recommendations'          => $issues['recommendations'],
			'total_posts'              => $issues['total_posts'],
			'posts_passing_essentials' => $issues['posts_passing_essentials'],
			'bots'                     => $this->events( 'bot_crawl' ),
			'referrals'                => $this->events( 'ai_referral' ),
			'sparkline'                => $this->sparkline( 'bot_crawl' ),
			'health'                   => $this->health(),
			'generated_at'             => time(),
		);
		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
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

	/**
	 * @return array{
	 *   essentials: array<string,int>,
	 *   recommendations: array<string,int>,
	 *   total_posts: int,
	 *   posts_passing_essentials: int
	 * }
	 */
	private function issues(): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT pm.post_id, pm.meta_value AS signals
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_geoboost_signals'
			AND p.post_status = 'publish'
			AND p.post_type IN ('post','page')"
		);

		$essential_map = array(
			'missing_article_schema' => 'has_article_schema',
			'heading_hierarchy'      => 'heading_hierarchy_ok',
			'word_count'             => 'word_count_ok',
			'missing_author_eeat'    => 'has_author_eeat',
		);
		$rec_map       = array(
			'missing_summary_block' => 'has_summary_block',
			'missing_excerpt'       => 'has_excerpt',
			'missing_key_facts'     => 'has_key_facts',
			'missing_faq'           => 'has_faq_signal',
			'missing_featured'      => 'has_featured',
			'missing_outbound_link' => 'has_outbound_link',
			'missing_internal_link' => 'has_internal_link',
		);

		$essentials      = array_fill_keys( array_keys( $essential_map ), 0 );
		$recommendations = array_fill_keys( array_keys( $rec_map ), 0 );

		$dismissals    = new Dismissals();
		$dismissed_map = array();
		foreach ( array_merge( array_keys( $essential_map ), array_keys( $rec_map ) ) as $type ) {
			$dismissed_map[ $type ] = array_flip( $dismissals->dismissed_ids_for_type( $type ) );
		}

		$total                    = 0;
		$posts_passing_essentials = 0;
		foreach ( (array) $rows as $row ) {
			++$total;
			$post_id = (int) $row->post_id;
			$d       = maybe_unserialize( $row->signals );
			if ( ! is_array( $d ) ) {
				continue;
			}
			$post_essential_failures = 0;
			foreach ( $essential_map as $type => $signal ) {
				if ( empty( $d[ $signal ] ) && ! isset( $dismissed_map[ $type ][ $post_id ] ) ) {
					++$essentials[ $type ];
					++$post_essential_failures;
				}
			}
			if ( 0 === $post_essential_failures ) {
				++$posts_passing_essentials;
			}
			foreach ( $rec_map as $type => $signal ) {
				if ( empty( $d[ $signal ] ) && ! isset( $dismissed_map[ $type ][ $post_id ] ) ) {
					++$recommendations[ $type ];
				}
			}
		}

		return array(
			'essentials'               => $essentials,
			'recommendations'          => $recommendations,
			'total_posts'              => $total,
			'posts_passing_essentials' => $posts_passing_essentials,
		);
	}
}
