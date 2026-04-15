<?php
/**
 * @var array $attributes
 */
$items = isset( $attributes['items'] ) && is_array( $attributes['items'] ) ? $attributes['items'] : array();
$items = array_values( array_filter( $items, static fn( $i ) => ! empty( $i['q'] ) && ! empty( $i['a'] ) ) );
if ( empty( $items ) ) {
	return '';
}
?>
<div class="wp-block-geoboost-faq">
	<?php foreach ( $items as $item ) : ?>
		<details>
			<summary><?php echo esc_html( $item['q'] ); ?></summary>
			<div class="geoboost-faq-answer"><?php echo wp_kses_post( $item['a'] ); ?></div>
		</details>
	<?php endforeach; ?>
</div>
