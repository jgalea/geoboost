<?php
namespace GeoBoost\Tests\Unit\Events;

use GeoBoost\Events\BotTracker;
use GeoBoost\Events\EventLogger;
use PHPUnit\Framework\TestCase;

final class BotTrackerTest extends TestCase {

	public function test_matches_gptbot_in_ua(): void {
		$rows = array();
		$log  = new EventLogger( static function ( array $r ) use ( &$rows ): void {
			$rows[] = $r;
		} );
		$bt   = new BotTracker( $log );
		$bt->process( array( 'ua' => 'Mozilla/5.0 (compatible; GPTBot/1.0)', 'path' => '/hello', 'referer' => '' ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'GPTBot', $rows[0]['bot_or_source'] );
	}

	public function test_ignores_non_bot_ua(): void {
		$rows = array();
		$log  = new EventLogger( static function ( array $r ) use ( &$rows ): void {
			$rows[] = $r;
		} );
		( new BotTracker( $log ) )->process( array( 'ua' => 'Mozilla/5.0 (Macintosh)', 'path' => '/', 'referer' => '' ) );
		$this->assertCount( 0, $rows );
	}

	public function test_matches_claudebot(): void {
		$rows = array();
		$log  = new EventLogger( static function ( array $r ) use ( &$rows ): void {
			$rows[] = $r;
		} );
		( new BotTracker( $log ) )->process( array( 'ua' => 'ClaudeBot/1.0', 'path' => '/', 'referer' => '' ) );
		$this->assertSame( 'ClaudeBot', $rows[0]['bot_or_source'] );
	}
}
