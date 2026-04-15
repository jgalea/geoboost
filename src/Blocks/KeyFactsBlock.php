<?php
namespace GeoBoost\Blocks;

final class KeyFactsBlock {
	public function register(): void {
		add_action(
			'init',
			static function (): void {
				register_block_type( GEOBOOST_DIR . 'build/key-facts' );
			}
		);
	}
}
