<?php
namespace GeoBoost\Core;

final class Plugin {

    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void {
        // Components registered in later tasks.
    }
}
