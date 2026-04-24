<?php
namespace GeoBoost\Tests\Unit\Core;

use GeoBoost\Core\Migrations;
use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase {

    public function test_current_version_is_at_least_4(): void {
        $reflection = new \ReflectionClass( Migrations::class );
        $constant   = $reflection->getConstant( 'CURRENT' );
        $this->assertGreaterThanOrEqual( 4, (int) $constant );
    }

    public function test_drop_score_meta_method_exists(): void {
        $this->assertTrue( method_exists( Migrations::class, 'drop_score_meta_v4' ) );
    }

    public function test_rename_details_to_signals_method_exists(): void {
        $this->assertTrue( method_exists( Migrations::class, 'rename_details_to_signals_v4' ) );
    }
}
