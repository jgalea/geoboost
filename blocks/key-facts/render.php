<?php
$facts = isset( $attributes['facts'] ) && is_array( $attributes['facts'] ) ? $attributes['facts'] : array();
$facts = array_values( array_filter( $facts, static fn( $f ) => ! empty( $f['k'] ) && ! empty( $f['v'] ) ) );
if ( empty( $facts ) ) {
	return '';
}
?>
<div class="wp-block-geoboost-key-facts">
	<strong><?php esc_html_e( 'Key Facts', 'geoboost' ); ?></strong>
	<dl>
		<?php foreach ( $facts as $f ) : ?>
			<dt><?php echo esc_html( $f['k'] ); ?></dt>
			<dd><?php echo esc_html( $f['v'] ); ?></dd>
		<?php endforeach; ?>
	</dl>
</div>
