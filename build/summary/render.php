<?php
defined( 'ABSPATH' ) || exit;

$geoboost_style     = isset( $attributes['style'] ) ? (string) $attributes['style'] : 'bulleted';
$geoboost_points    = isset( $attributes['points'] ) && is_array( $attributes['points'] ) ? array_values( array_filter( $attributes['points'], static fn( $p ) => '' !== trim( (string) $p ) ) ) : array();
$geoboost_paragraph = isset( $attributes['paragraph'] ) ? trim( (string) $attributes['paragraph'] ) : '';

if ( 'paragraph' === $geoboost_style && '' === $geoboost_paragraph ) {
	return '';
}
if ( 'paragraph' !== $geoboost_style && empty( $geoboost_points ) ) {
	return '';
}
?>
<div class="wp-block-geoboost-summary" itemprop="abstract">
	<strong><?php esc_html_e( 'TL;DR', 'geoboost' ); ?></strong>
	<?php if ( 'paragraph' === $geoboost_style ) : ?>
		<p><?php echo esc_html( $geoboost_paragraph ); ?></p>
	<?php elseif ( 'numbered' === $geoboost_style ) : ?>
		<ol>
			<?php foreach ( $geoboost_points as $geoboost_point ) : ?>
				<li><?php echo esc_html( $geoboost_point ); ?></li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<ul>
			<?php foreach ( $geoboost_points as $geoboost_point ) : ?>
				<li><?php echo esc_html( $geoboost_point ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
