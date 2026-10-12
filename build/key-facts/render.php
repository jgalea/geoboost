<?php
defined( 'ABSPATH' ) || exit;

$geoboost_facts = isset( $attributes['facts'] ) && is_array( $attributes['facts'] ) ? $attributes['facts'] : array();
$geoboost_facts = array_values( array_filter( $geoboost_facts, static fn( $f ) => ! empty( $f['k'] ) && ! empty( $f['v'] ) ) );
if ( empty( $geoboost_facts ) ) {
	return '';
}
?>
<div class="wp-block-geoboost-key-facts">
	<strong><?php esc_html_e( 'Key Facts', 'geoboost' ); ?></strong>
	<dl>
		<?php foreach ( $geoboost_facts as $geoboost_fact ) : ?>
			<dt><?php echo esc_html( $geoboost_fact['k'] ); ?></dt>
			<dd><?php echo esc_html( $geoboost_fact['v'] ); ?></dd>
		<?php endforeach; ?>
	</dl>
</div>
