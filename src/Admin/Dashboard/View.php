<?php
namespace GeoBoost\Admin\Dashboard;

use GeoBoost\Scan\Checks;

final class View {

	public function render( array $data ): void {
		echo '<div class="wrap geoboost-admin">';
		$this->header( $data );
		$this->kpis( $data );
		echo '<div class="geoboost-grid">';
		$this->site_scan_card( (array) ( $data['site_scan'] ?? array() ) );
		$this->score_distribution_card( $data['scores'] );
		$this->site_health_card( $data['health'] );
		$this->bots_card( $data['bots'] );
		$this->referrals_card( $data['referrals'] );
		$this->top_posts_card( $data['top_posts'] );
		$this->recommendations_card( $data['recommendations'], $data['scores']['total'] );
		echo '</div>';
		echo '</div>';
	}

	private function header( array $data ): void {
		printf(
			'<div class="geoboost-header"><h1>%s</h1><span class="geoboost-header__meta">%s</span></div>',
			esc_html__( 'GEOBoost Dashboard', 'geoboost' ),
			esc_html(
				sprintf(
					/* translators: %s: relative time */
					__( 'Updated %s', 'geoboost' ),
					human_time_diff( (int) $data['generated_at'], time() ) . ' ' . __( 'ago', 'geoboost' )
				)
			)
		);
	}

	private function kpis( array $data ): void {
		$scores    = $data['scores'];
		$bots      = $data['bots'];
		$referrals = $data['referrals'];

		echo '<div class="geoboost-kpis">';

		$this->kpi(
			__( 'Average GEO score', 'geoboost' ),
			$scores['total'] > 0 ? (string) $scores['average'] : '—',
			sprintf(
				/* translators: %d: count */
				_n( 'across %d scored post', 'across %d scored posts', (int) $scores['total'], 'geoboost' ),
				(int) $scores['total']
			),
			null
		);

		$this->kpi(
			__( 'AI bot crawls', 'geoboost' ),
			number_format_i18n( (int) $bots['total'] ),
			$this->delta_label( (int) $bots['delta'], $bots['percent'] ),
			Svg::sparkline( $data['sparkline'], __( 'Daily bot crawls', 'geoboost' ) ),
			$this->delta_class( (int) $bots['delta'] )
		);

		$this->kpi(
			__( 'AI referral clicks', 'geoboost' ),
			number_format_i18n( (int) $referrals['total'] ),
			$this->delta_label( (int) $referrals['delta'], $referrals['percent'] ),
			null,
			$this->delta_class( (int) $referrals['delta'] )
		);

		$top_bot = '';
		if ( ! empty( $bots['series'] ) ) {
			$top_bot = (string) array_key_first( $bots['series'] );
		}
		$this->kpi(
			__( 'Top crawler', 'geoboost' ),
			'' !== $top_bot ? $top_bot : '—',
			'' !== $top_bot ? sprintf(
				/* translators: %s: number of crawls */
				__( '%s crawls in 30d', 'geoboost' ),
				number_format_i18n( (int) $bots['series'][ $top_bot ] )
			) : __( 'No crawls detected yet', 'geoboost' ),
			null
		);

		echo '</div>';
	}

	private function kpi( string $label, string $value, string $sub, ?string $spark = null, string $delta_class = '' ): void {
		echo '<div class="geoboost-kpi">';
		echo '<span class="geoboost-kpi__label">' . esc_html( $label ) . '</span>';
		echo '<span class="geoboost-kpi__value">' . esc_html( $value ) . '</span>';
		if ( '' !== $sub ) {
			printf(
				'<span class="geoboost-kpi__delta%s">%s</span>',
				'' !== $delta_class ? ' ' . esc_attr( $delta_class ) : '',
				esc_html( $sub )
			);
		}
		if ( null !== $spark && '' !== $spark ) {
			echo '<div class="geoboost-kpi__spark">' . wp_kses( $spark, $this->svg_allowed() ) . '</div>';
		}
		echo '</div>';
	}

	private function delta_label( int $delta, ?int $percent ): string {
		if ( null === $percent ) {
			return __( 'vs. no prior data', 'geoboost' );
		}
		$arrow = $delta > 0 ? '▲' : ( $delta < 0 ? '▼' : '–' );
		return sprintf(
			/* translators: 1: arrow symbol, 2: percentage */
			__( '%1$s %2$s%% vs. prior 30d', 'geoboost' ),
			$arrow,
			number_format_i18n( abs( (int) $percent ) )
		);
	}

	private function delta_class( int $delta ): string {
		if ( $delta > 0 ) {
			return 'geoboost-kpi__delta--up';
		}
		if ( $delta < 0 ) {
			return 'geoboost-kpi__delta--down';
		}
		return '';
	}

	private function site_scan_card( array $scan ): void {
		$checks  = (array) ( $scan['checks'] ?? array() );
		$scanned = (int) ( $scan['scanned_at'] ?? 0 );
		$url     = (string) ( $scan['url'] ?? home_url( '/' ) );

		echo '<div class="geoboost-card geoboost-scan" id="geoboost-site-scan">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2><span class="geoboost-card__subtitle">%s</span></div>',
			esc_html__( 'AI visibility scan', 'geoboost' ),
			esc_html(
				$scanned > 0
					? sprintf(
						/* translators: 1: relative time, 2: URL */
						__( 'Scanned %1$s ago: %2$s', 'geoboost' ),
						human_time_diff( $scanned, time() ),
						$url
					)
					: __( 'Not run yet', 'geoboost' )
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['scan'] ) && 'invalid' === sanitize_key( wp_unslash( $_GET['scan'] ) ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Enter a path like /about/ or a full URL on this site.', 'geoboost' ) . '</p></div>';
		}

		printf(
			'<form class="geoboost-scan__form" method="post" action="%s"><input type="hidden" name="action" value="geoboost_site_scan" />',
			esc_url( admin_url( 'admin-post.php' ) )
		);
		wp_nonce_field( 'geoboost_site_scan' );
		printf(
			'<label for="geoboost-scan-url">%1$s</label>' .
			'<input type="text" id="geoboost-scan-url" name="geoboost_scan_url" class="regular-text" value="%2$s" />' .
			'<button type="submit" class="button button-primary">%3$s</button></form>',
			esc_html__( 'Page to scan', 'geoboost' ),
			esc_attr( $url ),
			$scanned > 0 ? esc_html__( 'Re-run scan', 'geoboost' ) : esc_html__( 'Run scan', 'geoboost' )
		);
		echo '<p class="geoboost-scan__note">' . esc_html__( 'Your server fetches the page, robots.txt, llms.txt and sitemap from your public URL, logged out, the way a crawler does. It also requests the page as GPTBot, ClaudeBot and PerplexityBot. Depending on your host these requests may skip your CDN, and firewalls that verify real crawlers by IP address may treat the test requests differently from real ones.', 'geoboost' ) . '</p>';

		if ( ! empty( $scan['error'] ) ) {
			echo '<p class="geoboost-scan__error">' . esc_html( (string) $scan['error'] ) . '</p>';
		}

		if ( empty( $checks ) ) {
			echo '</div>';
			return;
		}

		$score      = (int) ( $scan['score'] ?? 0 );
		$categories = Checks::categories();
		echo '<div class="geoboost-scan__summary">';
		printf(
			'<div class="geoboost-dist-hero__score"><span class="geoboost-pill geoboost-pill--%s">%d</span><span class="geoboost-dist-hero__label">%s</span></div>',
			esc_attr( $this->score_tier( $score ) ),
			(int) $score,
			esc_html__( 'Site score', 'geoboost' )
		);
		echo '<ul class="geoboost-scan__categories" role="list">';
		foreach ( $categories as $key => $label ) {
			$value = $scan['categories'][ $key ] ?? null;
			printf(
				'<li><span class="geoboost-scan__category-label">%s</span><span class="geoboost-scan__category-value">%s</span></li>',
				esc_html( $label ),
				null === $value ? '&ndash;' : esc_html( (int) $value . '%' )
			);
		}
		echo '</ul>';
		echo '</div>';

		$status_labels = array(
			'fail' => __( 'Fail', 'geoboost' ),
			'warn' => __( 'Warning', 'geoboost' ),
			'pass' => __( 'Pass', 'geoboost' ),
		);
		$tones         = array(
			'fail' => 'bad',
			'warn' => 'warn',
			'pass' => 'good',
		);
		foreach ( $categories as $key => $label ) {
			$in_cat = array_values(
				array_filter(
					$checks,
					static function ( $c ) use ( $key ): bool {
						return is_array( $c ) && ( $c['category'] ?? '' ) === $key;
					}
				)
			);
			if ( empty( $in_cat ) ) {
				continue;
			}
			$rank = array_flip( array_keys( $status_labels ) );
			usort(
				$in_cat,
				static function ( array $a, array $b ) use ( $rank ): int {
					return ( $rank[ $a['status'] ] ?? 3 ) <=> ( $rank[ $b['status'] ] ?? 3 );
				}
			);
			echo '<h3 class="geoboost-scan__heading">' . esc_html( $label ) . '</h3>';
			echo '<ul class="geoboost-scan__checks" role="list">';
			foreach ( $in_cat as $c ) {
				$status = isset( $status_labels[ $c['status'] ] ) ? $c['status'] : 'warn';
				printf(
					'<li class="geoboost-scan__check geoboost-scan__check--%1$s">' .
					'<span class="geoboost-dot geoboost-dot--%2$s" aria-hidden="true"></span>' .
					'<div class="geoboost-scan__body"><span class="geoboost-scan__title">%3$s <span class="geoboost-scan__status">%4$s</span></span>' .
					'<span class="geoboost-scan__summary-text">%5$s</span>%6$s</div></li>',
					esc_attr( $status ),
					esc_attr( $tones[ $status ] ),
					esc_html( (string) $c['title'] ),
					esc_html( $status_labels[ $status ] ),
					esc_html( (string) $c['summary'] ),
					'' !== (string) ( $c['fix'] ?? '' ) ? '<span class="geoboost-scan__fix">' . esc_html( (string) $c['fix'] ) . '</span>' : ''
				);
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	private function score_distribution_card( array $scores ): void {
		$total   = (int) $scores['total'];
		$average = (int) $scores['average'];
		$tier    = $this->score_tier( $average );

		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2><span class="geoboost-card__subtitle">%s</span></div>',
			esc_html__( 'Score distribution', 'geoboost' ),
			esc_html(
				sprintf(
					/* translators: %d: count */
					_n( '%d post', '%d posts', $total, 'geoboost' ),
					$total
				)
			)
		);

		if ( $total > 0 ) {
			printf(
				'<div class="geoboost-dist-hero">' .
				'<div class="geoboost-dist-hero__score"><span class="geoboost-pill geoboost-pill--%s">%d</span><span class="geoboost-dist-hero__label">%s</span></div>' .
				'<p class="geoboost-dist-hero__note">%s</p>' .
				'</div>',
				esc_attr( $tier ),
				(int) $average,
				esc_html__( 'Site average', 'geoboost' ),
				esc_html( $this->distribution_note( $scores['bins'], $total ) )
			);
		}

		echo wp_kses( Svg::segmented( $scores['bins'], $total ), $this->bar_allowed() );
		echo '</div>';
	}

	private function distribution_note( array $bins, int $total ): string {
		$good_or_better = (int) ( $bins['good'] ?? 0 ) + (int) ( $bins['excellent'] ?? 0 );
		$pct            = $total > 0 ? (int) round( ( $good_or_better / $total ) * 100 ) : 0;
		return sprintf(
			/* translators: 1: percentage, 2: count */
			__( '%1$d%% of posts (%2$d) are at Good or Excellent.', 'geoboost' ),
			$pct,
			$good_or_better
		);
	}

	private function bots_card( array $bots ): void {
		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2><span class="geoboost-card__subtitle">%s</span></div>',
			esc_html__( 'AI bot activity', 'geoboost' ),
			esc_html__( 'Last 30 days', 'geoboost' )
		);
		echo wp_kses( Svg::bars( $bots['series'], __( 'AI bot activity', 'geoboost' ) ), $this->bar_allowed() );
		echo '</div>';
	}

	private function referrals_card( array $referrals ): void {
		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2><span class="geoboost-card__subtitle">%s</span></div>',
			esc_html__( 'AI referral clicks', 'geoboost' ),
			esc_html__( 'Last 30 days', 'geoboost' )
		);
		echo wp_kses( Svg::bars( $referrals['series'], __( 'AI referral clicks', 'geoboost' ) ), $this->bar_allowed() );
		echo '</div>';
	}

	private function top_posts_card( array $top_posts ): void {
		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2></div>',
			esc_html__( 'Top performing posts', 'geoboost' )
		);
		if ( empty( $top_posts ) ) {
			echo '<p class="geoboost-empty">' . esc_html__( 'No scored posts yet.', 'geoboost' ) . '</p>';
			echo '</div>';
			return;
		}
		echo '<table class="geoboost-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Post', 'geoboost' ) . '</th>';
		echo '<th class="geoboost-table__meta">' . esc_html__( 'Scanned', 'geoboost' ) . '</th>';
		echo '<th class="geoboost-table__score">' . esc_html__( 'Score', 'geoboost' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $top_posts as $post ) {
			$tier    = $this->score_tier( (int) $post['score'] );
			$scanned = (int) ( $post['scanned'] ?? 0 );
			$when    = $scanned > 0
				? sprintf(
					/* translators: %s: relative time */
					__( '%s ago', 'geoboost' ),
					human_time_diff( $scanned, time() )
				)
				: '—';
			printf(
				'<tr><td class="geoboost-table__title"><a href="%s">%s</a></td>' .
				'<td class="geoboost-table__meta">%s</td>' .
				'<td class="geoboost-table__score"><span class="geoboost-pill geoboost-pill--%s">%d</span></td></tr>',
				esc_url( (string) $post['edit'] ),
				esc_html( (string) $post['title'] ),
				esc_html( $when ),
				esc_attr( $tier ),
				(int) $post['score']
			);
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	private function recommendations_card( array $counts, int $total_scored ): void {
		$labels = array(
			'missing_summary'     => __( 'posts missing a Summary block', 'geoboost' ),
			'missing_key_facts'   => __( 'posts missing Key Facts', 'geoboost' ),
			'missing_faq'         => __( 'posts missing FAQ signals', 'geoboost' ),
			'heading_hierarchy'   => __( 'posts with heading hierarchy issues', 'geoboost' ),
			'word_count'          => __( 'posts outside 300–3000 word sweet spot', 'geoboost' ),
			'missing_author_eeat' => __( 'posts with weak author E-E-A-T signals', 'geoboost' ),
			'missing_featured'    => __( 'posts without a featured image', 'geoboost' ),
		);
		arsort( $counts );

		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2><span class="geoboost-card__subtitle">%s</span></div>',
			esc_html__( 'Recommendations', 'geoboost' ),
			esc_html__( 'Biggest wins first', 'geoboost' )
		);

		if ( 0 === $total_scored ) {
			echo '<p class="geoboost-empty geoboost-empty--tall">' .
				esc_html__( 'No posts have been analyzed yet. Save any post to generate its GEO score.', 'geoboost' ) .
				'</p>';
			echo '</div>';
			return;
		}

		$has_recs = false;
		echo '<div class="geoboost-recs">';
		foreach ( $counts as $key => $count ) {
			if ( $count <= 0 ) {
				continue;
			}
			$has_recs = true;
			$url      = admin_url( 'admin.php?page=geoboost-fix&type=' . rawurlencode( $key ) );
			printf(
				'<a class="geoboost-rec" href="%s"><span class="geoboost-rec__count">%s</span>' .
				'<span class="geoboost-rec__body">%s</span><span class="geoboost-rec__arrow" aria-hidden="true">→</span></a>',
				esc_url( $url ),
				esc_html( number_format_i18n( (int) $count ) ),
				esc_html( $labels[ $key ] )
			);
		}
		if ( ! $has_recs ) {
			echo '<p class="geoboost-empty">' .
				esc_html__( 'Every post passes the baseline checks. Nice work.', 'geoboost' ) .
				'</p>';
		}
		echo '</div>';
		echo '</div>';
	}

	private function site_health_card( array $health ): void {
		$seo_names = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'All in One SEO',
			'seopress' => 'SEOPress',
			'surerank' => 'SureRank',
			'tsf'      => 'The SEO Framework',
		);
		$seo_list  = array();
		foreach ( (array) $health['seo_plugins'] as $slug ) {
			$seo_list[] = $seo_names[ $slug ] ?? (string) $slug;
		}
		$seo_label  = empty( $seo_list )
			? __( 'None detected (GEOBoost will emit full schema)', 'geoboost' )
			: implode( ', ', $seo_list );
		$llms_label = $health['llms_ok']
			? __( 'Served by GEOBoost', 'geoboost' )
			: __( 'Overridden by another source', 'geoboost' );
		$llms_tone  = $health['llms_ok'] ? 'good' : 'warn';

		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2></div>',
			esc_html__( 'Site health', 'geoboost' )
		);
		echo '<ul class="geoboost-health" role="list">';
		printf(
			'<li><span class="geoboost-health__label">%s</span><span class="geoboost-health__value">%s</span></li>',
			esc_html__( 'SEO plugin coexistence', 'geoboost' ),
			esc_html( $seo_label )
		);
		printf(
			'<li><span class="geoboost-health__label">%s</span><span class="geoboost-health__value"><span class="geoboost-dot geoboost-dot--%s"></span>%s</span></li>',
			esc_html__( '/llms.txt endpoint', 'geoboost' ),
			esc_attr( $llms_tone ),
			esc_html( $llms_label )
		);
		printf(
			'<li><span class="geoboost-health__label">%s</span><span class="geoboost-health__value">%s</span></li>',
			esc_html__( 'AI bots blocked via robots.txt', 'geoboost' ),
			esc_html(
				sprintf(
					/* translators: %d: count */
					_n( '%d bot', '%d bots', (int) $health['bots_blocked'], 'geoboost' ),
					(int) $health['bots_blocked']
				)
			)
		);
		echo '</ul>';
		echo '</div>';
	}

	private function score_tier( int $score ): string {
		if ( $score >= 80 ) {
			return 'excellent';
		}
		if ( $score >= 60 ) {
			return 'good';
		}
		if ( $score >= 40 ) {
			return 'fair';
		}
		return 'poor';
	}

	/**
	 * @return array<string,array<string,bool|array<int,string>>>
	 */
	private function svg_allowed(): array {
		return array(
			'svg'      => array(
				'class'               => true,
				'role'                => true,
				'aria-label'          => true,
				'viewBox'             => true,
				'preserveAspectRatio' => true,
			),
			'polyline' => array(
				'class'  => true,
				'points' => true,
			),
			'polygon'  => array(
				'class'  => true,
				'points' => true,
			),
		);
	}

	/**
	 * @return array<string,array<string,bool>>
	 */
	private function bar_allowed(): array {
		return array(
			'div'    => array(
				'class' => true,
				'role'  => true,
			),
			'ul'     => array(
				'class'      => true,
				'role'       => true,
				'aria-label' => true,
			),
			'li'     => array(
				'class' => true,
			),
			'span'   => array(
				'class'       => true,
				'style'       => true,
				'title'       => true,
				'aria-hidden' => true,
			),
			'strong' => array(
				'class' => true,
			),
			'p'      => array(
				'class' => true,
			),
		);
	}
}
