<?php
namespace GeoBoost\Analyzer;

final class Scorer {

	private const WEIGHTS = array(
		'has_summary'          => 10,
		'has_key_facts'        => 10,
		'has_faq_signal'       => 10,
		'heading_hierarchy_ok' => 10,
		'word_count_ok'        => 10,
		'has_author_eeat'      => 10,
		'has_article_schema'   => 15,
		'has_custom_jsonld'    => 5,
		'has_featured'         => 5,
		'has_outbound_link'    => 5,
		'has_internal_link'    => 5,
		'recent'               => 5,
	);

	public function score( array $signals ): int {
		$total = 0;
		foreach ( self::WEIGHTS as $key => $points ) {
			if ( ! empty( $signals[ $key ] ) ) {
				$total += $points;
			}
		}
		return max( 0, min( 100, $total ) );
	}

	public function append_history( array $history, int $ts, int $score ): array {
		$last = end( $history );
		if ( is_array( $last ) && (int) ( $last['score'] ?? -1 ) === $score ) {
			return $history;
		}
		$history[] = array(
			'ts'    => $ts,
			'score' => $score,
		);
		if ( count( $history ) > 52 ) {
			$history = array_slice( $history, -52 );
		}
		return array_values( $history );
	}
}
