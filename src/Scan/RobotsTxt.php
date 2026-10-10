<?php
namespace GeoBoost\Scan;

final class RobotsTxt {

	/** @var array<int,array{agents:array<int,string>,rules:array<int,array{allow:bool,path:string}>}> */
	private array $groups = array();

	/** @var array<int,string> */
	private array $sitemaps = array();

	public function __construct( string $text ) {
		$current        = null;
		$last_was_agent = false;

		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $raw ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $raw ) );
			$idx  = strpos( $line, ':' );
			if ( '' === $line || false === $idx ) {
				continue;
			}
			$key   = strtolower( trim( substr( $line, 0, $idx ) ) );
			$value = trim( substr( $line, $idx + 1 ) );

			if ( 'user-agent' === $key ) {
				if ( null === $current || ! $last_was_agent ) {
					$this->groups[] = array(
						'agents' => array(),
						'rules'  => array(),
					);
					$current        = count( $this->groups ) - 1;
				}
				$this->groups[ $current ]['agents'][] = strtolower( $value );
				$last_was_agent                       = true;
			} elseif ( 'allow' === $key || 'disallow' === $key ) {
				$last_was_agent = false;
				// An empty Disallow allows everything, so it adds no rule.
				if ( null === $current || ( 'disallow' === $key && '' === $value ) ) {
					continue;
				}
				$this->groups[ $current ]['rules'][] = array(
					'allow' => 'allow' === $key,
					'path'  => $value,
				);
			} elseif ( 'sitemap' === $key ) {
				if ( '' !== $value ) {
					$this->sitemaps[] = $value;
				}
			} else {
				$last_was_agent = false;
			}
		}
	}

	/**
	 * @return array<int,string>
	 */
	public function sitemaps(): array {
		return $this->sitemaps;
	}

	/**
	 * Google-style matching: groups naming the crawler win over "*", and the
	 * longest matching pattern wins, with Allow winning a tie.
	 */
	public function is_allowed( string $agent, string $path ): bool {
		$token    = strtolower( $agent );
		$specific = array();
		$wildcard = array();
		foreach ( $this->groups as $group ) {
			if ( in_array( $token, $group['agents'], true ) && '*' !== $token ) {
				$specific[] = $group;
			}
			if ( in_array( '*', $group['agents'], true ) ) {
				$wildcard[] = $group;
			}
		}

		$best = null;
		foreach ( ( $specific ? $specific : $wildcard ) as $group ) {
			foreach ( $group['rules'] as $rule ) {
				if ( ! self::pattern_matches( $rule['path'], $path ) ) {
					continue;
				}
				$len = strlen( $rule['path'] );
				if ( null === $best || $len > $best['len'] || ( $len === $best['len'] && $rule['allow'] ) ) {
					$best = array(
						'len'   => $len,
						'allow' => $rule['allow'],
					);
				}
			}
		}

		return null === $best || $best['allow'];
	}

	private static function pattern_matches( string $pattern, string $path ): bool {
		$anchored = '' !== $pattern && '$' === substr( $pattern, -1 );
		$body     = $anchored ? substr( $pattern, 0, -1 ) : $pattern;
		$parts    = array_map(
			static function ( string $part ): string {
				return preg_quote( $part, '#' );
			},
			explode( '*', $body )
		);
		return 1 === preg_match( '#^' . implode( '.*', $parts ) . ( $anchored ? '$' : '' ) . '#', $path );
	}
}
