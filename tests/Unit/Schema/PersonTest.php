<?php
namespace GeoBoost\Tests\Unit\Schema;

use GeoBoost\Schema\Person;
use PHPUnit\Framework\TestCase;

final class PersonTest extends TestCase {

	public function test_build_minimal(): void {
		$p = ( new Person() )->build( array(
			'name' => 'Jane Doe',
			'url'  => 'https://x.test/author/jane/',
		) );
		$this->assertSame( 'Person', $p['@type'] );
		$this->assertSame( 'Jane Doe', $p['name'] );
		$this->assertSame( 'https://x.test/author/jane/', $p['url'] );
		$this->assertArrayNotHasKey( 'sameAs', $p );
	}

	public function test_build_with_social_profiles(): void {
		$p = ( new Person() )->build( array(
			'name'      => 'Jane',
			'url'       => 'https://x.test/a/jane/',
			'sameAs'    => array( 'https://linkedin.com/in/jane', 'https://x.com/jane' ),
			'knowsAbout'=> array( 'SEO', 'Content' ),
			'jobTitle'  => 'Editor',
		) );
		$this->assertCount( 2, $p['sameAs'] );
		$this->assertSame( array( 'SEO', 'Content' ), $p['knowsAbout'] );
		$this->assertSame( 'Editor', $p['jobTitle'] );
	}

	public function test_build_strips_empty_social(): void {
		$p = ( new Person() )->build( array(
			'name'   => 'Jane',
			'url'    => 'https://x.test',
			'sameAs' => array( 'https://x.com/jane', '', null ),
		) );
		$this->assertSame( array( 'https://x.com/jane' ), array_values( $p['sameAs'] ) );
	}
}
