<?php
namespace GeoBoost\Admin;

use GeoBoost\Robots\AiBotController;

final class SettingsPage {

	private const SLUG       = 'geoboost';
	private const OPTION_GRP = 'geoboost_options';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_override_notice' ) );
	}

	public function maybe_show_override_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_geoboost' !== $screen->id ) {
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
		add_menu_page(
			__( 'GEOBoost', 'geoboost' ),
			__( 'GEOBoost', 'geoboost' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-admin-site-alt3',
			81
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
		<div class="wrap">
			<h1><?php esc_html_e( 'GEOBoost', 'geoboost' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: URL */
					esc_html__( 'Your llms.txt is served at %s', 'geoboost' ),
					'<code>' . esc_html( home_url( '/llms.txt' ) ) . '</code>'
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GRP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
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
		echo '<fieldset>';
		foreach ( AiBotController::known_bots() as $bot ) {
			printf(
				'<label><input type="checkbox" name="geoboost_blocked_ai_bots[]" value="%1$s" %2$s /> <strong>%3$s</strong> — %4$s</label><br>',
				esc_attr( $bot['user_agent'] ),
				checked( in_array( $bot['user_agent'], $blocked, true ), true, false ),
				esc_html( $bot['label'] ),
				esc_html( $bot['purpose'] )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Selected bots will be added to robots.txt with Disallow: / directives.', 'geoboost' ) . '</p>';
	}
}
