<?php
namespace GeoBoost\Tests\Unit\Core;

use GeoBoost\Core\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {

    public function test_instance_returns_same_object(): void {
        $a = Plugin::instance();
        $b = Plugin::instance();
        $this->assertSame( $a, $b );
    }

    public function test_constructor_is_private(): void {
        $reflection = new \ReflectionClass( Plugin::class );
        $this->assertTrue( $reflection->getConstructor()->isPrivate() );
    }
}
