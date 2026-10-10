<?php
namespace GeoBoost\Tests\Unit\Robots;

use GeoBoost\Core\Settings;
use GeoBoost\Robots\AiBotController;
use PHPUnit\Framework\TestCase;

final class AiBotControllerTest extends TestCase {

    private array $store = [];

    protected function setUp(): void {
        $this->store = [];
        Settings::set_test_accessors(
            fn( string $k, $d = null ) => $this->store[ $k ] ?? $d,
            function ( string $k, $v ): void { $this->store[ $k ] = $v; }
        );
    }

    public function test_known_bots_contains_gptbot(): void {
        $bots = AiBotController::known_bots();
        $slugs = array_column( $bots, 'user_agent' );
        $this->assertContains( 'GPTBot', $slugs );
    }

    public function test_filter_appends_disallow_for_blocked_bots(): void {
        $this->store['geoboost_blocked_ai_bots'] = [ 'GPTBot', 'ClaudeBot' ];

        $c      = new AiBotController( new Settings() );
        $output = $c->filter_robots_txt( "User-agent: *\nAllow: /\n", true );

        $this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $output );
        $this->assertStringContainsString( "User-agent: ClaudeBot\nDisallow: /", $output );
    }

    public function test_filter_returns_input_unchanged_when_nothing_blocked(): void {
        $input = "User-agent: *\nAllow: /\n";

        $c      = new AiBotController( new Settings() );
        $output = $c->filter_robots_txt( $input, true );

        $this->assertSame( $input, $output );
    }

    public function test_filter_skips_when_site_is_not_public(): void {
        $this->store['geoboost_blocked_ai_bots'] = [ 'GPTBot' ];

        $c      = new AiBotController( new Settings() );
        $output = $c->filter_robots_txt( "User-agent: *\nDisallow: /\n", false );

        $this->assertStringNotContainsString( 'GPTBot', $output );
    }

    public function test_unknown_bot_slug_is_ignored(): void {
        $this->store['geoboost_blocked_ai_bots'] = [ 'EvilBot' ];

        $c      = new AiBotController( new Settings() );
        $output = $c->filter_robots_txt( "User-agent: *\n", true );

        $this->assertStringNotContainsString( 'EvilBot', $output );
    }

    public function test_every_known_bot_has_a_kind(): void {
        foreach ( AiBotController::known_bots() as $bot ) {
            $this->assertContains( $bot['kind'], [ 'search', 'training' ], $bot['user_agent'] );
        }
    }

    public function test_blocked_of_kind_splits_search_from_training(): void {
        $blocked = [ 'GPTBot', 'OAI-SearchBot', 'PerplexityBot', 'EvilBot' ];

        $search   = array_column( AiBotController::blocked_of_kind( $blocked, 'search' ), 'user_agent' );
        $training = array_column( AiBotController::blocked_of_kind( $blocked, 'training' ), 'user_agent' );

        $this->assertSame( [ 'OAI-SearchBot', 'PerplexityBot' ], $search );
        $this->assertSame( [ 'GPTBot' ], $training );
    }
}
