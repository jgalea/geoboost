<?php
namespace GeoBoost\Tests\Unit\Events;

use GeoBoost\Events\EventLogger;
use GeoBoost\Events\ReferralTracker;
use PHPUnit\Framework\TestCase;

final class ReferralTrackerTest extends TestCase {

	public function test_matches_chatgpt_referer(): void {
		$rows = array();
		$log  = new EventLogger( static function ( array $r ) use ( &$rows ): void {
			$rows[] = $r;
		} );
		( new ReferralTracker( $log ) )->process( array(
			'referer'    => 'https://chatgpt.com/c/abc123',
			'path'       => '/hello',
			'utm_source' => '',
		) );
		$this->assertSame( 'chatgpt', $rows[0]['bot_or_source'] );
	}

	public function test_matches_utm_source(): void {
		$rows = array();
		$log  = new EventLogger( static function ( array $r ) use ( &$rows ): void {
			$rows[] = $r;
		} );
		( new ReferralTracker( $log ) )->process( array(
			'referer'    => '',
			'path'       => '/hello',
			'utm_source' => 'perplexity',
		) );
		$this->assertSame( 'perplexity', $rows[0]['bot_or_source'] );
	}

	public function test_ignores_google_referer(): void {
		$rows = array();
		$log  = new EventLogger( static function ( array $r ) use ( &$rows ): void {
			$rows[] = $r;
		} );
		( new ReferralTracker( $log ) )->process( array(
			'referer'    => 'https://www.google.com/search?q=x',
			'path'       => '/',
			'utm_source' => '',
		) );
		$this->assertCount( 0, $rows );
	}
}
