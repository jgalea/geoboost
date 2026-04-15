<?php
namespace GeoBoost\Admin\MetaBoxes;

use GeoBoost\Schema\CustomJsonLd;

final class CustomJsonLdBox {

	private const META_KEY = '_geoboost_custom_jsonld';
	private const NONCE    = 'geoboost_custom_jsonld_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	public function add_box(): void {
		foreach ( array( 'post', 'page' ) as $screen ) {
			add_meta_box(
				'geoboost_custom_jsonld',
				__( 'GEOBoost — Custom JSON-LD', 'geoboost' ),
				array( $this, 'render' ),
				$screen,
				'normal',
				'low'
			);
		}
	}

	public function render( \WP_Post $post ): void {
		$value = (string) get_post_meta( $post->ID, self::META_KEY, true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p><?php esc_html_e( 'Paste custom JSON-LD to add to this post. Must include @context and @type. Leave empty to skip.', 'geoboost' ); ?></p>
		<textarea name="geoboost_custom_jsonld" rows="10" class="widefat code" style="font-family:monospace"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// JSON-LD must retain quotes/braces; sanitize_textarea_field would break it. We validate as JSON below.
		$raw    = isset( $_POST['geoboost_custom_jsonld'] ) ? wp_unslash( $_POST['geoboost_custom_jsonld'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result = ( new CustomJsonLd() )->validate( (string) $raw );
		if ( ! $result['ok'] ) {
			set_transient( 'geoboost_jsonld_error_' . get_current_user_id(), $result['error'], 60 );
			return;
		}
		update_post_meta( $post_id, self::META_KEY, wp_slash( trim( (string) $raw ) ) );
	}
}
