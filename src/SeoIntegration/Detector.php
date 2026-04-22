<?php
namespace GeoBoost\SeoIntegration;

final class Detector {

	private const PLUGIN_MAP = array(
		'yoast'    => array( 'WPSEO_Options', 'WPSEO_Frontend' ),
		'rankmath' => array( 'RankMath' ),
		'aioseo'   => array( 'AIOSEO\\Plugin\\AIOSEO' ),
		'seopress' => array( 'SEOPRESS_VERSION_NUM' ),
		'surerank' => array( 'SureRank\\Inc\\Plugin_Loader' ),
		'tsf'      => array( 'THE_SEO_FRAMEWORK_VERSION', 'The_SEO_Framework\\Load' ),
	);

	/** @var array<string,bool> Override for tests. */
	private array $override;

	public function __construct( array $override = array() ) {
		$this->override = $override;
	}

	/**
	 * @return array<int,string>
	 */
	public function active(): array {
		$active = array();
		foreach ( self::PLUGIN_MAP as $slug => $symbols ) {
			if ( isset( $this->override[ $slug ] ) ) {
				if ( $this->override[ $slug ] ) {
					$active[] = $slug;
				}
				continue;
			}
			foreach ( $symbols as $symbol ) {
				if ( class_exists( $symbol ) || defined( $symbol ) ) {
					$active[] = $slug;
					break;
				}
			}
		}
		return $active;
	}

	public function html_emits_type( string $html, string $schema_type ): bool {
		if ( ! preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.+?)</script>#is', $html, $m ) ) {
			return false;
		}
		foreach ( $m[1] as $json ) {
			$data = json_decode( trim( $json ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			if ( $this->tree_contains_type( $data, $schema_type ) ) {
				return true;
			}
		}
		return false;
	}

	private function tree_contains_type( $node, string $schema_type ): bool {
		if ( ! is_array( $node ) ) {
			return false;
		}
		if ( isset( $node['@type'] ) ) {
			$types = (array) $node['@type'];
			foreach ( $types as $t ) {
				if ( strcasecmp( (string) $t, $schema_type ) === 0 ) {
					return true;
				}
			}
		}
		foreach ( $node as $v ) {
			if ( is_array( $v ) && $this->tree_contains_type( $v, $schema_type ) ) {
				return true;
			}
		}
		return false;
	}
}
