<?php
namespace GeoBoost\Tests\Unit\LlmsTxt;

use GeoBoost\LlmsTxt\Cache;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase {

    private array $store = [];

    protected function setUp(): void {
        $this->store = [];
        Cache::set_test_accessors(
            fn( string $k ) => $this->store[ $k ] ?? false,
            function ( string $k, string $v, int $ttl ): void {
                $this->store[ $k ] = $v;
            },
            function ( string $k ): void {
                unset( $this->store[ $k ] );
            }
        );
    }

    public function test_get_returns_null_when_missing(): void {
        $this->assertNull( ( new Cache() )->get() );
    }

    public function test_set_then_get(): void {
        $c = new Cache();
        $c->set( 'hello' );
        $this->assertSame( 'hello', $c->get() );
    }

    public function test_flush_removes_value(): void {
        $c = new Cache();
        $c->set( 'x' );
        $c->flush();
        $this->assertNull( $c->get() );
    }
}
