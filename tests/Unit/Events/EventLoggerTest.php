<?php
namespace GeoBoost\Tests\Unit\Events;

use GeoBoost\Events\EventLogger;
use PHPUnit\Framework\TestCase;

final class EventLoggerTest extends TestCase {

	public function test_log_writes_row(): void {
		$rows   = array();
		$logger = new EventLogger( static function ( array $row ) use ( &$rows ): void {
			$rows[] = $row;
		} );
		$logger->log( 'bot_crawl', array(
			'bot_or_source' => 'GPTBot',
			'path'          => '/hello',
			'status_code'   => 200,
			'user_agent'    => 'Mozilla/5.0 GPTBot',
			'referer'       => '',
		) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'bot_crawl', $rows[0]['event_type'] );
		$this->assertSame( 'GPTBot', $rows[0]['bot_or_source'] );
		$this->assertArrayHasKey( 'created_at', $rows[0] );
	}

	public function test_log_truncates_user_agent(): void {
		$rows   = array();
		$logger = new EventLogger( static function ( array $row ) use ( &$rows ): void {
			$rows[] = $row;
		} );
		$ua = str_repeat( 'x', 5000 );
		$logger->log( 'bot_crawl', array( 'bot_or_source' => 'X', 'user_agent' => $ua, 'path' => '/', 'status_code' => 200, 'referer' => '' ) );
		$this->assertLessThanOrEqual( 1000, strlen( $rows[0]['user_agent'] ) );
	}
}
