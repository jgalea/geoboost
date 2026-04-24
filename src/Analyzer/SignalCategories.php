<?php
namespace GeoBoost\Analyzer;

final class SignalCategories {

	/** @return array<int,string> */
	public static function essentials(): array {
		return array(
			'has_article_schema',
			'heading_hierarchy_ok',
			'word_count_ok',
			'has_author_eeat',
		);
	}

	/** @return array<int,string> */
	public static function recommended(): array {
		return array(
			'has_summary_block',
			'has_excerpt',
			'has_key_facts',
			'has_faq_signal',
			'has_featured',
			'has_outbound_link',
			'has_internal_link',
		);
	}

	public static function category_for( string $signal ): ?string {
		if ( in_array( $signal, self::essentials(), true ) ) {
			return 'essential';
		}
		if ( in_array( $signal, self::recommended(), true ) ) {
			return 'recommended';
		}
		return null;
	}
}
