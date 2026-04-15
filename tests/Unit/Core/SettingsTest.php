<?php
namespace GeoBoost\Tests\Unit\Core;

use GeoBoost\Core\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

    private array $store = [];

    protected function setUp(): void {
        $this->store = [];
        Settings::set_test_accessors(
            fn( string $k, $d = null ) => $this->store[ $k ] ?? $d,
            function ( string $k, $v ): void { $this->store[ $k ] = $v; }
        );
    }

    public function test_get_returns_default_when_unset(): void {
        $s = new Settings();
        $this->assertSame( 'fallback', $s->get( 'missing', 'fallback' ) );
    }

    public function test_set_then_get_roundtrip(): void {
        $s = new Settings();
        $s->set( 'foo', 'bar' );
        $this->assertSame( 'bar', $s->get( 'foo' ) );
    }

    public function test_namespaced_option_key(): void {
        $s = new Settings();
        $s->set( 'x', 1 );
        $this->assertArrayHasKey( 'geoboost_x', $this->store );
    }
}
