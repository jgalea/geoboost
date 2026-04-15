<?php
namespace GeoBoost\Admin\MetaBoxes;

final class LlmsTxtEntryBox {

	private const NONCE = 'geoboost_llmstxt_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	public function add_box(): void {
		foreach ( array( 'post', 'page' ) as $pt ) {
			add_meta_box(
				'geoboost_llmstxt_entry',
				__( 'GEOBoost — llms.txt entry', 'geoboost' ),
				array( $this, 'render' ),
				$pt,
				'side',
				'low'
			);
		}
	}

	public function render( \WP_Post $post ): void {
		$summary  = (string) get_post_meta( $post->ID, '_geoboost_llmstxt_summary', true );
		$excluded = '1' === (string) get_post_meta( $post->ID, '_geoboost_llmstxt_exclude', true );
		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<p><label for="geoboost_llmstxt_summary">' . esc_html__( 'Custom llms.txt summary (optional)', 'geoboost' ) . '</label>';
		printf(
			'<textarea name="geoboost_llmstxt_summary" id="geoboost_llmstxt_summary" rows="3" class="widefat">%s</textarea></p>',
			esc_textarea( $summary )
		);
		printf(
			'<p><label><input type="checkbox" name="geoboost_llmstxt_exclude" value="1" %s /> %s</label></p>',
			checked( $excluded, true, false ),
			esc_html__( 'Exclude from llms.txt', 'geoboost' )
		);
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_geoboost_llmstxt_summary', sanitize_textarea_field( wp_unslash( $_POST['geoboost_llmstxt_summary'] ?? '' ) ) );
		$excluded = ! empty( $_POST['geoboost_llmstxt_exclude'] ) ? '1' : '';
		if ( '' === $excluded ) {
			delete_post_meta( $post_id, '_geoboost_llmstxt_exclude' );
		} else {
			update_post_meta( $post_id, '_geoboost_llmstxt_exclude', '1' );
		}
	}
}
