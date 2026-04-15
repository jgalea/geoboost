<?php
$style     = isset( $attributes['style'] ) ? (string) $attributes['style'] : 'bulleted';
$points    = isset( $attributes['points'] ) && is_array( $attributes['points'] ) ? array_values( array_filter( $attributes['points'], static fn( $p ) => '' !== trim( (string) $p ) ) ) : array();
$paragraph = isset( $attributes['paragraph'] ) ? trim( (string) $attributes['paragraph'] ) : '';

if ( 'paragraph' === $style && '' === $paragraph ) {
	return '';
}
if ( 'paragraph' !== $style && empty( $points ) ) {
	return '';
}
?>
<div class="wp-block-geoboost-summary" itemprop="abstract">
	<strong><?php esc_html_e( 'TL;DR', 'geoboost' ); ?></strong>
	<?php if ( 'paragraph' === $style ) : ?>
		<p><?php echo esc_html( $paragraph ); ?></p>
	<?php elseif ( 'numbered' === $style ) : ?>
		<ol>
			<?php foreach ( $points as $p ) : ?>
				<li><?php echo esc_html( $p ); ?></li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<ul>
			<?php foreach ( $points as $p ) : ?>
				<li><?php echo esc_html( $p ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
