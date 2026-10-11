<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var array $attributes
 */
$geoboost_items = isset( $attributes['items'] ) && is_array( $attributes['items'] ) ? $attributes['items'] : array();
$geoboost_items = array_values( array_filter( $geoboost_items, static fn( $i ) => ! empty( $i['q'] ) && ! empty( $i['a'] ) ) );
if ( empty( $geoboost_items ) ) {
	return '';
}
?>
<div class="wp-block-geoboost-faq">
	<?php foreach ( $geoboost_items as $geoboost_item ) : ?>
		<details>
			<summary><?php echo esc_html( $geoboost_item['q'] ); ?></summary>
			<div class="geoboost-faq-answer"><?php echo wp_kses_post( $geoboost_item['a'] ); ?></div>
		</details>
	<?php endforeach; ?>
</div>
