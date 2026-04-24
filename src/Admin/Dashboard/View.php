<?php
namespace GeoBoost\Admin\Dashboard;

final class View {

	public function render( array $data ): void {
		echo '<div class="wrap geoboost-admin">';
		$this->header( $data );
		$this->kpis( $data );
		echo '<div class="geoboost-grid">';
		$this->essentials_card( $data['essentials'], $data['total_posts'] );
		$this->recommendations_card( $data['recommendations'], $data['total_posts'] );
		$this->site_health_card( $data['health'] );
		$this->bots_card( $data['bots'] );
		$this->referrals_card( $data['referrals'] );
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
		$bots      = $data['bots'];
		$referrals = $data['referrals'];

		echo '<div class="geoboost-kpis">';

		$passing     = (int) ( $data['posts_passing_essentials'] ?? 0 );
		$total_posts = (int) ( $data['total_posts'] ?? 0 );
		$passing_pct = $total_posts > 0 ? (int) round( ( $passing / $total_posts ) * 100 ) : 0;
		$this->kpi(
			__( 'Posts passing essentials', 'geoboost' ),
			$total_posts > 0 ? $passing_pct . '%' : '—',
			sprintf(
				/* translators: 1: pass count, 2: total */
				__( '%1$d of %2$d posts', 'geoboost' ),
				$passing,
				$total_posts
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

	private function essentials_card( array $counts, int $total_posts ): void {
		$labels = array(
			'missing_article_schema' => __( 'posts missing Article schema', 'geoboost' ),
			'heading_hierarchy'      => __( 'posts with heading hierarchy issues', 'geoboost' ),
			'word_count'             => __( 'posts under 150 words', 'geoboost' ),
			'missing_author_eeat'    => __( 'posts with weak author E-E-A-T signals', 'geoboost' ),
		);
		arsort( $counts );

		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2><span class="geoboost-card__subtitle">%s</span></div>',
			esc_html__( 'Essential issues', 'geoboost' ),
			esc_html__( 'Fix these first', 'geoboost' )
		);

		if ( 0 === $total_posts ) {
			echo '<p class="geoboost-empty geoboost-empty--tall">' .
				esc_html__( 'No posts have been analyzed yet. Save any post to evaluate.', 'geoboost' ) .
				'</p></div>';
			return;
		}

		$has_issues = false;
		echo '<div class="geoboost-recs">';
		foreach ( $counts as $key => $count ) {
			if ( $count <= 0 ) {
				continue;
			}
			$has_issues = true;
			$url        = admin_url( 'admin.php?page=geoboost-fix&type=' . rawurlencode( $key ) );
			printf(
				'<a class="geoboost-rec geoboost-rec--essential" href="%s"><span class="geoboost-rec__count">%s</span>' .
				'<span class="geoboost-rec__body">%s</span><span class="geoboost-rec__arrow" aria-hidden="true">→</span></a>',
				esc_url( $url ),
				esc_html( number_format_i18n( (int) $count ) ),
				esc_html( $labels[ $key ] )
			);
		}
		if ( ! $has_issues ) {
			echo '<p class="geoboost-empty">' .
				esc_html__( 'All posts pass the essentials. Excellent.', 'geoboost' ) .
				'</p>';
		}
		echo '</div>';
		echo '</div>';
	}

	private function recommendations_card( array $counts, int $total_posts ): void {
		$labels = array(
			'missing_summary_block' => __( 'posts missing a Summary block', 'geoboost' ),
			'missing_excerpt'       => __( 'posts missing an excerpt', 'geoboost' ),
			'missing_key_facts'     => __( 'posts missing Key Facts', 'geoboost' ),
			'missing_faq'           => __( 'posts missing FAQ signals', 'geoboost' ),
			'missing_featured'      => __( 'posts without a featured image', 'geoboost' ),
			'missing_outbound_link' => __( 'posts without outbound citations', 'geoboost' ),
			'missing_internal_link' => __( 'posts without internal links', 'geoboost' ),
		);
		arsort( $counts );

		echo '<div class="geoboost-card geoboost-card--6">';
		printf(
			'<div class="geoboost-card__header"><h2>%s</h2><span class="geoboost-card__subtitle">%s</span></div>',
			esc_html__( 'Recommendations', 'geoboost' ),
			esc_html__( 'Biggest wins first', 'geoboost' )
		);

		if ( 0 === $total_posts ) {
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
