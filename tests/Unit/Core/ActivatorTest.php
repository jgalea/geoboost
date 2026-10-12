<?php
namespace GeoBoost\Tests\Unit\Core;

use GeoBoost\Core\Activator;
use PHPUnit\Framework\TestCase;

final class ActivatorTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['geoboost_test_calls'], $GLOBALS['geoboost_test_options'] );
	}

	public function test_llms_rule_is_registered_before_the_flush(): void {
		$GLOBALS['geoboost_test_options'] = array( 'geoboost_db_version' => '999' );
		$GLOBALS['geoboost_test_calls']   = array();

		Activator::activate();

		$names = array_column( $GLOBALS['geoboost_test_calls'], 0 );
		$rule  = array_search( array( 'add_rewrite_rule', '^llms\.txt$' ), $GLOBALS['geoboost_test_calls'], true );
		$flush = array_search( 'flush_rewrite_rules', $names, true );

		$this->assertNotFalse( $rule );
		$this->assertNotFalse( $flush );
		$this->assertLessThan( $flush, $rule );
	}
}
