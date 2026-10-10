<?php
namespace GeoBoost\Admin;

use GeoBoost\Robots\AiBotController;

final class SettingsPage {

	private const SLUG       = 'geoboost-settings';
	private const OPTION_GRP = 'geoboost_options';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_override_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_blocked_bots_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'geoboost_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'geoboost-admin',
			GEOBOOST_URL . 'assets/admin/admin.css',
			array(),
			GEOBOOST_VERSION
		);
		wp_enqueue_style(
			'geoboost-settings',
			GEOBOOST_URL . 'assets/admin/settings.css',
			array( 'geoboost-admin' ),
			GEOBOOST_VERSION
		);
	}

	public function maybe_show_override_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'geoboost_page_geoboost-settings' !== $screen->id ) {
			return;
		}
		$detector = new \GeoBoost\LlmsTxt\OverrideDetector();
		if ( ! $detector->check_cached( home_url( '/llms.txt' ) ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'GEOBoost: /llms.txt is being overridden', 'geoboost' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: URL */
					esc_html__( 'Another source is serving %s before GEOBoost can. This usually means a static file exists at your web root or another plugin is handling the URL. Remove or rename that source so GEOBoost can serve its auto-generated llms.txt.', 'geoboost' ),
					'<code>' . esc_html( home_url( '/llms.txt' ) ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	public function add_menu(): void {
		add_submenu_page(
			'geoboost',
			__( 'Settings', 'geoboost' ),
			__( 'Settings', 'geoboost' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GRP,
			'geoboost_description',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GRP,
			'geoboost_include_posts',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $v ) => (bool) $v,
				'default'           => true,
			)
		);
		register_setting(
			self::OPTION_GRP,
			'geoboost_include_pages',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $v ) => (bool) $v,
				'default'           => true,
			)
		);
		register_setting(
			self::OPTION_GRP,
			'geoboost_blocked_ai_bots',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_bots' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'main', __( 'General', 'geoboost' ), '__return_false', self::SLUG );

		add_settings_field(
			'geoboost_description',
			__( 'Site description', 'geoboost' ),
			array( $this, 'field_description' ),
			self::SLUG,
			'main'
		);
		add_settings_field(
			'geoboost_include_posts',
			__( 'Include posts in llms.txt', 'geoboost' ),
			array( $this, 'field_include_posts' ),
			self::SLUG,
			'main'
		);
		add_settings_field(
			'geoboost_include_pages',
			__( 'Include pages in llms.txt', 'geoboost' ),
			array( $this, 'field_include_pages' ),
			self::SLUG,
			'main'
		);
		add_settings_field(
			'geoboost_blocked_ai_bots',
			__( 'Block AI bots', 'geoboost' ),
			array( $this, 'field_blocked_bots' ),
			self::SLUG,
			'main'
		);

		foreach ( array( 'article', 'person', 'organization', 'website' ) as $type ) {
			register_setting(
				self::OPTION_GRP,
				'geoboost_schema_mode_' . $type,
				array(
					'type'              => 'string',
					'sanitize_callback' => static function ( $v ): string {
						return in_array( $v, array( 'auto', 'force', 'off' ), true ) ? $v : 'auto';
					},
					'default'           => 'auto',
				)
			);
		}

		add_settings_section(
			'schema',
			__( 'Schema output', 'geoboost' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Control whether GEOBoost outputs each schema type. Auto = skip if your SEO plugin already emits it.', 'geoboost' ) . '</p>';
			},
			self::SLUG
		);

		foreach ( array(
			'article'      => __( 'Article', 'geoboost' ),
			'person'       => __( 'Person (author archives)', 'geoboost' ),
			'organization' => __( 'Organization (front page)', 'geoboost' ),
			'website'      => __( 'WebSite + SearchAction (front page)', 'geoboost' ),
		) as $key => $label ) {
			add_settings_field(
				'geoboost_schema_mode_' . $key,
				$label,
				function () use ( $key ): void {
					$v = (string) get_option( 'geoboost_schema_mode_' . $key, 'auto' );
					echo '<div class="geoboost-schema-mode">';
					foreach ( array(
						'auto'  => __( 'Auto', 'geoboost' ),
						'force' => __( 'Force', 'geoboost' ),
						'off'   => __( 'Off', 'geoboost' ),
					) as $opt => $opt_label ) {
						printf(
							'<label><input type="radio" name="geoboost_schema_mode_%1$s" value="%2$s" %3$s /><span>%4$s</span></label>',
							esc_attr( $key ),
							esc_attr( $opt ),
							checked( $v, $opt, false ),
							esc_html( $opt_label )
						);
					}
					echo '</div>';
				},
				self::SLUG,
				'schema'
			);
		}
	}

	/**
	 * @param mixed $value
	 * @return array<int,string>
	 */
	public function sanitize_bots( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$known = array_column( AiBotController::known_bots(), 'user_agent' );
		return array_values( array_intersect( $value, $known ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap geoboost-admin">
			<div class="geoboost-header">
				<h1><?php esc_html_e( 'GEOBoost Settings', 'geoboost' ); ?></h1>
			</div>
			<div class="geoboost-settings">
				<p class="geoboost-settings__intro">
					<?php
					printf(
						/* translators: %s: URL */
						esc_html__( 'Your llms.txt is served at %s', 'geoboost' ),
						'<code>' . esc_html( home_url( '/llms.txt' ) ) . '</code>'
					);
					?>
				</p>
				<form method="post" action="options.php">
					<?php settings_fields( self::OPTION_GRP ); ?>
					<div class="geoboost-card geoboost-settings__section">
						<?php $this->render_section( 'main' ); ?>
					</div>
					<div class="geoboost-card geoboost-settings__section">
						<?php $this->render_section( 'schema' ); ?>
					</div>
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	private function render_section( string $section_id ): void {
		global $wp_settings_sections, $wp_settings_fields;
		if ( ! isset( $wp_settings_sections[ self::SLUG ][ $section_id ] ) ) {
			return;
		}
		$section = $wp_settings_sections[ self::SLUG ][ $section_id ];
		if ( ! empty( $section['title'] ) ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
		}
		if ( ! empty( $section['callback'] ) ) {
			call_user_func( $section['callback'], $section );
		}
		if ( ! isset( $wp_settings_fields[ self::SLUG ][ $section_id ] ) ) {
			return;
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		do_settings_fields( self::SLUG, $section_id );
		echo '</tbody></table>';
	}

	public function field_description(): void {
		$v = (string) get_option( 'geoboost_description', '' );
		printf(
			'<textarea name="geoboost_description" rows="4" cols="60" class="large-text">%s</textarea>',
			esc_textarea( $v )
		);
		echo '<p class="description">' . esc_html__( 'Overrides the tagline in /llms.txt. Use this to describe what your site is about in 1-3 sentences.', 'geoboost' ) . '</p>';
	}

	public function field_include_posts(): void {
		$v = (bool) get_option( 'geoboost_include_posts', true );
		printf(
			'<label><input type="checkbox" name="geoboost_include_posts" value="1" %s /> %s</label>',
			checked( $v, true, false ),
			esc_html__( 'Include blog posts', 'geoboost' )
		);
	}

	public function field_include_pages(): void {
		$v = (bool) get_option( 'geoboost_include_pages', true );
		printf(
			'<label><input type="checkbox" name="geoboost_include_pages" value="1" %s /> %s</label>',
			checked( $v, true, false ),
			esc_html__( 'Include pages', 'geoboost' )
		);
	}

	public function field_blocked_bots(): void {
		$blocked = (array) get_option( 'geoboost_blocked_ai_bots', array() );
		echo '<div class="geoboost-bot-list">';
		foreach ( AiBotController::known_bots() as $bot ) {
			$search = 'search' === $bot['kind'];
			printf(
				'<label class="geoboost-bot-list__item--%1$s"><input type="checkbox" name="geoboost_blocked_ai_bots[]" value="%2$s" %3$s /><span class="geoboost-bot-list__text"><span class="geoboost-bot-list__label">%4$s</span><span class="geoboost-bot-list__purpose">%5$s</span><span class="geoboost-bot-list__kind">%6$s</span></span></label>',
				esc_attr( $bot['kind'] ),
				esc_attr( $bot['user_agent'] ),
				checked( in_array( $bot['user_agent'], $blocked, true ), true, false ),
				esc_html( $bot['label'] ),
				esc_html( $bot['purpose'] ),
				$search ? esc_html__( 'Live AI answers: blocking removes you from them', 'geoboost' ) : esc_html__( 'Model training', 'geoboost' )
			);
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Selected bots will be added to robots.txt with Disallow: / directives.', 'geoboost' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Search crawlers fetch your pages to cite them in live answers in ChatGPT search, Perplexity and similar tools. Block one and your site stops appearing in that tool\'s answers. Training crawlers collect data for future models; blocking them is a business choice and doesn\'t affect live answers.', 'geoboost' ) . '</p>';
	}

	public function maybe_show_blocked_bots_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'geoboost_page_geoboost-settings' !== $screen->id ) {
			return;
		}
		$blocked  = (array) get_option( 'geoboost_blocked_ai_bots', array() );
		$search   = AiBotController::blocked_of_kind( $blocked, 'search' );
		$training = AiBotController::blocked_of_kind( $blocked, 'training' );

		if ( $search ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong><br>%s</p></div>',
				esc_html__( 'GEOBoost: you are blocking AI search crawlers', 'geoboost' ),
				esc_html(
					sprintf(
						/* translators: %s: comma-separated list of crawler names */
						__( 'robots.txt now tells %s to stay away. These crawlers fetch pages to cite in live AI answers, so your site will drop out of those answers. Untick them below unless that is what you want.', 'geoboost' ),
						implode( ', ', array_column( $search, 'label' ) )
					)
				)
			);
		} elseif ( $training ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: comma-separated list of crawler names */
						__( 'GEOBoost is blocking %s. These are training crawlers, so live AI answers are unaffected, but future models will know less about your site.', 'geoboost' ),
						implode( ', ', array_column( $training, 'label' ) )
					)
				)
			);
		}
	}
}
