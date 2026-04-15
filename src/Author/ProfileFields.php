<?php
namespace GeoBoost\Author;

final class ProfileFields {

	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
	}

	public function render( \WP_User $user ): void {
		$credentials = (string) get_user_meta( $user->ID, 'geoboost_credentials', true );
		$expertise   = implode( ', ', (array) get_user_meta( $user->ID, 'geoboost_expertise', true ) );
		$same_as     = implode( "\n", (array) get_user_meta( $user->ID, 'geoboost_sameas', true ) );
		$job_title   = (string) get_user_meta( $user->ID, 'geoboost_job_title', true );
		wp_nonce_field( 'geoboost_profile', 'geoboost_profile_nonce' );
		?>
		<h2><?php esc_html_e( 'GEOBoost Author Signals (E-E-A-T)', 'geoboost' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="geoboost_job_title"><?php esc_html_e( 'Job title', 'geoboost' ); ?></label></th>
				<td><input type="text" name="geoboost_job_title" id="geoboost_job_title" value="<?php echo esc_attr( $job_title ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="geoboost_credentials"><?php esc_html_e( 'Credentials', 'geoboost' ); ?></label></th>
				<td>
					<textarea name="geoboost_credentials" id="geoboost_credentials" rows="3" class="large-text"><?php echo esc_textarea( $credentials ); ?></textarea>
					<p class="description"><?php esc_html_e( 'e.g. "Certified Financial Planner, 15 years in European P2P".', 'geoboost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="geoboost_expertise"><?php esc_html_e( 'Expertise areas', 'geoboost' ); ?></label></th>
				<td>
					<input type="text" name="geoboost_expertise" id="geoboost_expertise" value="<?php echo esc_attr( $expertise ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Comma-separated topics.', 'geoboost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="geoboost_sameas"><?php esc_html_e( 'Social / authority URLs', 'geoboost' ); ?></label></th>
				<td>
					<textarea name="geoboost_sameas" id="geoboost_sameas" rows="4" class="large-text"><?php echo esc_textarea( $same_as ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line — LinkedIn, X, Mastodon, personal site, publications.', 'geoboost' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save( int $user_id ): void {
		if ( ! isset( $_POST['geoboost_profile_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['geoboost_profile_nonce'] ) ), 'geoboost_profile' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		update_user_meta( $user_id, 'geoboost_job_title', sanitize_text_field( wp_unslash( $_POST['geoboost_job_title'] ?? '' ) ) );
		update_user_meta( $user_id, 'geoboost_credentials', sanitize_textarea_field( wp_unslash( $_POST['geoboost_credentials'] ?? '' ) ) );
		// Sanitized by sanitize_expertise / sanitize_urls below.
		update_user_meta( $user_id, 'geoboost_expertise', $this->sanitize_expertise( (string) wp_unslash( $_POST['geoboost_expertise'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$urls_raw = preg_split( '/\r?\n/', (string) wp_unslash( $_POST['geoboost_sameas'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_user_meta( $user_id, 'geoboost_sameas', $this->sanitize_urls( $urls_raw ) );
	}

	public function sanitize_expertise( string $csv ): array {
		$items = array_filter( array_map( 'trim', explode( ',', $csv ) ) );
		$seen  = array();
		$out   = array();
		foreach ( $items as $item ) {
			$norm = strtolower( $item );
			if ( isset( $seen[ $norm ] ) ) {
				continue;
			}
			$seen[ $norm ] = true;
			$out[]         = ucwords( $item, " \t\r\n\f\v-" );
		}
		return $out;
	}

	public function sanitize_urls( array $urls ): array {
		$out = array();
		foreach ( $urls as $url ) {
			$url = trim( $url );
			if ( '' === $url ) {
				continue;
			}
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}
			$out[] = $url;
		}
		return $out;
	}
}
