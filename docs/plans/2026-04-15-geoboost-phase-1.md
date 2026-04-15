# GEOBoost Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a working, shippable GEOBoost plugin MVP covering plugin bootstrap, `llms.txt` generation/serving, and robots.txt AI-bot controls — the "plumbing + simplest two features."

**Architecture:** Standard WordPress plugin, PSR-4 autoloaded under `GeoBoost\` namespace, WP-native Settings API, no custom tables, no external HTTP calls. Activation registers a rewrite rule for `/llms.txt`; content is cached in a transient and invalidated on `save_post`. AI bot blocking hooks into the `robots_txt` filter.

**Tech Stack:** PHP 7.4+, WordPress 6.0+, Composer (dev only), PHPUnit + WP test harness, WPCS (phpcs), @wordpress/scripts (Phase 3 only).

**Phase scope (this plan):** Tasks 1–14 below. Schema output, Gutenberg blocks, author signals, and dashboard are deferred to Phases 2–4 plans.

**Working directory:** `~/Library/CloudStorage/Dropbox/Work/WordPress/geoboost/`

---

## File Structure (Phase 1)

```
geoboost/
├── geoboost.php                            # Main plugin file (header, bootstrap)
├── uninstall.php                           # Cleanup on plugin delete
├── readme.txt                              # WP.org readme
├── LICENSE                                 # GPL v2
├── composer.json                           # Dev-only autoload + tools
├── phpunit.xml.dist                        # PHPUnit config
├── phpcs.xml.dist                          # WPCS ruleset
├── .gitignore                              # Local only, not shipped
├── src/
│   ├── Core/
│   │   ├── Plugin.php                      # Orchestrator singleton
│   │   ├── Activator.php                   # Activation hook
│   │   ├── Deactivator.php                 # Deactivation hook
│   │   └── Settings.php                    # Options API wrapper
│   ├── Admin/
│   │   └── SettingsPage.php                # Admin menu + render
│   ├── LlmsTxt/
│   │   ├── Generator.php                   # Builds llms.txt content
│   │   ├── Endpoint.php                    # Rewrite rule + request handler
│   │   └── Cache.php                       # Transient cache + invalidation
│   └── Robots/
│       └── AiBotController.php             # robots_txt filter + settings field
└── tests/
    ├── bootstrap.php                       # PHPUnit bootstrap
    ├── Unit/
    │   ├── Core/
    │   │   └── SettingsTest.php
    │   ├── LlmsTxt/
    │   │   ├── GeneratorTest.php
    │   │   └── CacheTest.php
    │   └── Robots/
    │       └── AiBotControllerTest.php
    └── Integration/
        └── LlmsTxtEndpointTest.php
```

---

## Task 1: Project Scaffolding + Composer

**Files:**
- Create: `geoboost.php`
- Create: `composer.json`
- Create: `.gitignore`
- Create: `LICENSE`
- Create: `phpcs.xml.dist`

- [ ] **Step 1: Initialize git**

```bash
cd ~/Library/CloudStorage/Dropbox/Work/WordPress/geoboost
git init -b main
```

- [ ] **Step 2: Create `.gitignore`**

```
/vendor/
/node_modules/
/build/
.phpunit.result.cache
.DS_Store
*.log
```

- [ ] **Step 3: Create `LICENSE` (GPL v2)**

Download the canonical text:
```bash
curl -s https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt > LICENSE
```

- [ ] **Step 4: Create `composer.json`**

```json
{
    "name": "jeangalea/geoboost",
    "description": "GEO (Generative Engine Optimization) plugin for WordPress.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "yoast/phpunit-polyfills": "^2.0",
        "wp-coding-standards/wpcs": "^3.0",
        "phpcompatibility/phpcompatibility-wp": "^2.1"
    },
    "autoload": {
        "psr-4": {
            "GeoBoost\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "GeoBoost\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "lint": "phpcs",
        "lint:fix": "phpcbf"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
```

- [ ] **Step 5: Install deps**

```bash
composer install
```

Expected: `vendor/` directory appears, no errors.

- [ ] **Step 6: Create `phpcs.xml.dist`**

```xml
<?xml version="1.0"?>
<ruleset name="GEOBoost">
    <description>Coding standards for GEOBoost.</description>
    <file>./src</file>
    <file>./geoboost.php</file>
    <file>./uninstall.php</file>
    <arg name="extensions" value="php"/>
    <arg name="colors"/>
    <arg value="sp"/>

    <rule ref="WordPress">
        <exclude name="WordPress.Files.FileName.InvalidClassFileName"/>
        <exclude name="WordPress.Files.FileName.NotHyphenatedLowercase"/>
    </rule>

    <rule ref="PHPCompatibilityWP"/>
    <config name="testVersion" value="7.4-"/>
    <config name="minimum_supported_wp_version" value="6.0"/>

    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array" value="geoboost"/>
        </properties>
    </rule>
</ruleset>
```

- [ ] **Step 7: Create main plugin file `geoboost.php` (header only, no logic yet)**

```php
<?php
/**
 * Plugin Name: GEOBoost
 * Description: Generative Engine Optimization for WordPress. Make your site AI-citable.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Jean Galea
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: geoboost
 */

defined( 'ABSPATH' ) || exit;

define( 'GEOBOOST_VERSION', '0.1.0' );
define( 'GEOBOOST_FILE', __FILE__ );
define( 'GEOBOOST_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEOBOOST_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';
```

- [ ] **Step 8: Commit scaffolding**

```bash
git add .
git commit -m "chore: initial scaffolding"
```

---

## Task 2: PHPUnit Harness

**Files:**
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`

- [ ] **Step 1: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    stopOnFailure="false">
    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 2: Create `tests/bootstrap.php`**

```php
<?php
/**
 * PHPUnit bootstrap for unit tests (no WP).
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Minimal WP function stubs for unit tests that reference WP globals.
// Integration tests that need the full WP stack use a separate harness.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/fake-wp/' );
}
```

- [ ] **Step 3: Verify PHPUnit runs (zero tests = pass)**

```bash
vendor/bin/phpunit --testsuite unit
```

Expected: `No tests executed!` (warning) but exit code 0.

- [ ] **Step 4: Commit**

```bash
git add phpunit.xml.dist tests/bootstrap.php
git commit -m "chore: add phpunit harness"
```

---

## Task 3: Core\Plugin Singleton

**Files:**
- Create: `src/Core/Plugin.php`
- Create: `tests/Unit/Core/PluginTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Core/PluginTest.php`:
```php
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
```

- [ ] **Step 2: Run the test, confirm failure**

```bash
vendor/bin/phpunit --filter PluginTest
```

Expected: FAIL — `Class "GeoBoost\Core\Plugin" not found`.

- [ ] **Step 3: Implement `src/Core/Plugin.php`**

```php
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
```

- [ ] **Step 4: Run test, confirm pass**

```bash
vendor/bin/phpunit --filter PluginTest
```

Expected: 2 tests pass.

- [ ] **Step 5: Wire bootstrap in `geoboost.php`**

Append to `geoboost.php`:
```php
add_action( 'plugins_loaded', static function (): void {
    \GeoBoost\Core\Plugin::instance()->boot();
} );
```

- [ ] **Step 6: Commit**

```bash
git add src/Core/Plugin.php tests/Unit/Core/PluginTest.php geoboost.php
git commit -m "feat(core): add Plugin singleton and bootstrap"
```

---

## Task 4: Settings Wrapper (Options API)

**Files:**
- Create: `src/Core/Settings.php`
- Create: `tests/Unit/Core/SettingsTest.php`

Settings is a thin wrapper around `get_option`/`update_option` with defaults and type safety. Tests use mocked option values via a static stub (no WP needed).

- [ ] **Step 1: Write the failing test**

`tests/Unit/Core/SettingsTest.php`:
```php
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
```

- [ ] **Step 2: Run, confirm failure**

```bash
vendor/bin/phpunit --filter SettingsTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement `src/Core/Settings.php`**

```php
<?php
namespace GeoBoost\Core;

final class Settings {

    private const PREFIX = 'geoboost_';

    /** @var callable|null */
    private static $getter = null;
    /** @var callable|null */
    private static $setter = null;

    public function get( string $key, $default = null ) {
        $getter = self::$getter ?? 'get_option';
        return $getter( self::PREFIX . $key, $default );
    }

    public function set( string $key, $value ): void {
        $setter = self::$setter ?? 'update_option';
        $setter( self::PREFIX . $key, $value );
    }

    /**
     * Test seam — only used from PHPUnit.
     */
    public static function set_test_accessors( ?callable $getter, ?callable $setter ): void {
        self::$getter = $getter;
        self::$setter = $setter;
    }
}
```

- [ ] **Step 4: Run test, confirm pass**

```bash
vendor/bin/phpunit --filter SettingsTest
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Settings.php tests/Unit/Core/SettingsTest.php
git commit -m "feat(core): add Settings wrapper for Options API"
```

---

## Task 5: Activator + Deactivator

**Files:**
- Create: `src/Core/Activator.php`
- Create: `src/Core/Deactivator.php`
- Create: `uninstall.php`
- Modify: `geoboost.php`

Activation/deactivation register the rewrite rule for `/llms.txt` and flush. Uninstall removes all plugin options.

- [ ] **Step 1: Implement `src/Core/Activator.php`**

```php
<?php
namespace GeoBoost\Core;

final class Activator {
    public static function activate(): void {
        // The rewrite rule itself is added on init; we just need to flush.
        // Endpoint class re-adds the rule on every init, so flushing now
        // after requiring the plugin file is safe.
        flush_rewrite_rules( false );
    }
}
```

- [ ] **Step 2: Implement `src/Core/Deactivator.php`**

```php
<?php
namespace GeoBoost\Core;

final class Deactivator {
    public static function deactivate(): void {
        flush_rewrite_rules( false );
    }
}
```

- [ ] **Step 3: Implement `uninstall.php`**

```php
<?php
/**
 * Runs when the plugin is deleted from the WP admin.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'geoboost\\_%'"
);

delete_transient( 'geoboost_llms_txt' );
```

- [ ] **Step 4: Register hooks in `geoboost.php`**

Append:
```php
register_activation_hook( __FILE__, [ \GeoBoost\Core\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \GeoBoost\Core\Deactivator::class, 'deactivate' ] );
```

- [ ] **Step 5: Commit**

```bash
git add src/Core/Activator.php src/Core/Deactivator.php uninstall.php geoboost.php
git commit -m "feat(core): add activation, deactivation, and uninstall"
```

---

## Task 6: LlmsTxt\Generator — Build Content

**Files:**
- Create: `src/LlmsTxt/Generator.php`
- Create: `tests/Unit/LlmsTxt/GeneratorTest.php`

Generator builds the `llms.txt` body from site metadata + a list of posts. Takes site name, tagline, description, home URL, and an array of post arrays (title, url, excerpt). No WP dependency inside `build()` — pure function on input.

The emerging `llms.txt` format (see llmstxt.org):
```
# {Site Title}

> {Tagline}

{Description}

## Posts

- [{Title}]({URL}): {One-line summary}
```

- [ ] **Step 1: Write the failing test**

`tests/Unit/LlmsTxt/GeneratorTest.php`:
```php
<?php
namespace GeoBoost\Tests\Unit\LlmsTxt;

use GeoBoost\LlmsTxt\Generator;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase {

    public function test_build_renders_site_header(): void {
        $out = ( new Generator() )->build(
            [
                'site_title'   => 'Jean Galea',
                'tagline'      => 'Entrepreneur & Polymath',
                'description'  => 'Long-form writing on business, tech, and life.',
                'home_url'     => 'https://jeangalea.com',
            ],
            []
        );

        $this->assertStringContainsString( '# Jean Galea', $out );
        $this->assertStringContainsString( '> Entrepreneur & Polymath', $out );
        $this->assertStringContainsString( 'Long-form writing on business, tech, and life.', $out );
    }

    public function test_build_includes_posts_section(): void {
        $out = ( new Generator() )->build(
            [ 'site_title' => 'S', 'tagline' => 'T', 'description' => 'D', 'home_url' => 'https://x.test' ],
            [
                [
                    'title'   => 'Hello World',
                    'url'     => 'https://x.test/hello',
                    'excerpt' => 'First post.',
                ],
            ]
        );

        $this->assertStringContainsString( '## Posts', $out );
        $this->assertStringContainsString( '[Hello World](https://x.test/hello)', $out );
        $this->assertStringContainsString( 'First post.', $out );
    }

    public function test_build_omits_posts_section_when_empty(): void {
        $out = ( new Generator() )->build(
            [ 'site_title' => 'S', 'tagline' => '', 'description' => '', 'home_url' => 'https://x.test' ],
            []
        );

        $this->assertStringNotContainsString( '## Posts', $out );
    }

    public function test_build_strips_newlines_from_excerpt(): void {
        $out = ( new Generator() )->build(
            [ 'site_title' => 'S', 'tagline' => '', 'description' => '', 'home_url' => 'https://x.test' ],
            [ [ 'title' => 'T', 'url' => 'https://x.test/t', 'excerpt' => "line1\nline2" ] ]
        );

        $this->assertStringNotContainsString( "line1\nline2", $out );
        $this->assertStringContainsString( 'line1 line2', $out );
    }
}
```

- [ ] **Step 2: Run, confirm failure**

```bash
vendor/bin/phpunit --filter GeneratorTest
```

- [ ] **Step 3: Implement `src/LlmsTxt/Generator.php`**

```php
<?php
namespace GeoBoost\LlmsTxt;

final class Generator {

    /**
     * @param array{site_title:string,tagline:string,description:string,home_url:string} $site
     * @param array<int,array{title:string,url:string,excerpt:string}>                    $posts
     */
    public function build( array $site, array $posts ): string {
        $lines = [];

        $lines[] = '# ' . $site['site_title'];
        $lines[] = '';

        if ( ! empty( $site['tagline'] ) ) {
            $lines[] = '> ' . $site['tagline'];
            $lines[] = '';
        }

        if ( ! empty( $site['description'] ) ) {
            $lines[] = $site['description'];
            $lines[] = '';
        }

        if ( ! empty( $posts ) ) {
            $lines[] = '## Posts';
            $lines[] = '';
            foreach ( $posts as $post ) {
                $excerpt = $this->flatten_whitespace( $post['excerpt'] );
                $entry   = sprintf( '- [%s](%s)', $post['title'], $post['url'] );
                if ( '' !== $excerpt ) {
                    $entry .= ': ' . $excerpt;
                }
                $lines[] = $entry;
            }
            $lines[] = '';
        }

        return implode( "\n", $lines );
    }

    private function flatten_whitespace( string $text ): string {
        return trim( preg_replace( '/\s+/', ' ', $text ) );
    }
}
```

- [ ] **Step 4: Run test, confirm pass**

```bash
vendor/bin/phpunit --filter GeneratorTest
```

Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/LlmsTxt/Generator.php tests/Unit/LlmsTxt/GeneratorTest.php
git commit -m "feat(llms-txt): add Generator for llms.txt body"
```

---

## Task 7: LlmsTxt\Cache — Transient Wrapper

**Files:**
- Create: `src/LlmsTxt/Cache.php`
- Create: `tests/Unit/LlmsTxt/CacheTest.php`

Wraps transient API with a test seam. Key: `geoboost_llms_txt`. TTL: 12 hours. Invalidated on `save_post`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/LlmsTxt/CacheTest.php`:
```php
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
```

- [ ] **Step 2: Run, confirm failure**

```bash
vendor/bin/phpunit --filter CacheTest
```

- [ ] **Step 3: Implement `src/LlmsTxt/Cache.php`**

```php
<?php
namespace GeoBoost\LlmsTxt;

final class Cache {

    private const KEY = 'geoboost_llms_txt';
    private const TTL = 12 * HOUR_IN_SECONDS;

    /** @var callable|null */
    private static $getter = null;
    /** @var callable|null */
    private static $setter = null;
    /** @var callable|null */
    private static $deleter = null;

    public function get(): ?string {
        $getter = self::$getter ?? 'get_transient';
        $value  = $getter( self::KEY );
        return false === $value ? null : (string) $value;
    }

    public function set( string $content ): void {
        $setter = self::$setter ?? 'set_transient';
        $setter( self::KEY, $content, defined( 'HOUR_IN_SECONDS' ) ? self::TTL : 43200 );
    }

    public function flush(): void {
        $deleter = self::$deleter ?? 'delete_transient';
        $deleter( self::KEY );
    }

    public static function set_test_accessors( ?callable $getter, ?callable $setter, ?callable $deleter ): void {
        self::$getter  = $getter;
        self::$setter  = $setter;
        self::$deleter = $deleter;
    }
}
```

Note: `HOUR_IN_SECONDS` is a WP constant; unit tests don't load WP so we fall back to `43200` (12 * 3600).

- [ ] **Step 4: Run, confirm pass**

```bash
vendor/bin/phpunit --filter CacheTest
```

- [ ] **Step 5: Commit**

```bash
git add src/LlmsTxt/Cache.php tests/Unit/LlmsTxt/CacheTest.php
git commit -m "feat(llms-txt): add transient Cache wrapper"
```

---

## Task 8: LlmsTxt\Endpoint — Rewrite Rule + Handler

**Files:**
- Create: `src/LlmsTxt/Endpoint.php`
- Create: `tests/Integration/LlmsTxtEndpointTest.php`
- Modify: `src/Core/Plugin.php`

Endpoint registers a query var and a rewrite rule mapping `/llms.txt` to `index.php?geoboost_llms=1`. On `template_redirect`, if the query var is set, emits the generated content with `Content-Type: text/plain; charset=utf-8` and exits. Data sourcing: WP site options + `get_posts()` (latest 50 published posts).

This task has an integration test because it needs WP rewrite rules. For simplicity we'll write the code and rely on manual validation plus the unit-level seams. The integration test is marked skipped until WP test harness is set up (Phase 1 ships without integration tests running in CI; manual smoke in Task 13).

- [ ] **Step 1: Implement `src/LlmsTxt/Endpoint.php`**

```php
<?php
namespace GeoBoost\LlmsTxt;

final class Endpoint {

    private const QUERY_VAR = 'geoboost_llms';

    private Generator $generator;
    private Cache $cache;

    public function __construct( Generator $generator, Cache $cache ) {
        $this->generator = $generator;
        $this->cache     = $cache;
    }

    public function register(): void {
        add_action( 'init', [ $this, 'add_rewrite' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
        add_action( 'save_post', [ $this, 'on_save_post' ] );
    }

    public function add_rewrite(): void {
        add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
    }

    public function add_query_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function maybe_serve(): void {
        if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
            return;
        }

        $content = $this->cache->get();
        if ( null === $content ) {
            $content = $this->generate();
            $this->cache->set( $content );
        }

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function on_save_post( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $this->cache->flush();
    }

    private function generate(): string {
        $site = [
            'site_title'  => get_bloginfo( 'name' ),
            'tagline'     => get_bloginfo( 'description' ),
            'description' => (string) apply_filters( 'geoboost_llms_description', get_bloginfo( 'description' ) ),
            'home_url'    => home_url( '/' ),
        ];

        $query_args = [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query_args = (array) apply_filters( 'geoboost_llms_query_args', $query_args );

        $posts = [];
        foreach ( get_posts( $query_args ) as $p ) {
            $posts[] = [
                'title'   => get_the_title( $p ),
                'url'     => (string) get_permalink( $p ),
                'excerpt' => $this->excerpt( $p ),
            ];
        }

        $posts = (array) apply_filters( 'geoboost_llms_posts', $posts );

        return $this->generator->build( $site, $posts );
    }

    private function excerpt( \WP_Post $post ): string {
        $raw = $post->post_excerpt;
        if ( '' === trim( $raw ) ) {
            $raw = wp_strip_all_tags( $post->post_content );
            $raw = wp_trim_words( $raw, 30, '' );
        }
        return trim( $raw );
    }
}
```

- [ ] **Step 2: Wire into `src/Core/Plugin.php`**

Replace the `boot()` method:
```php
public function boot(): void {
    ( new \GeoBoost\LlmsTxt\Endpoint(
        new \GeoBoost\LlmsTxt\Generator(),
        new \GeoBoost\LlmsTxt\Cache()
    ) )->register();
}
```

- [ ] **Step 3: Lint check**

```bash
vendor/bin/phpcs src/LlmsTxt/Endpoint.php
```

Expected: no errors. Fix any surfaced (WPCS will flag docblocks, output escaping, etc. — address them before moving on).

- [ ] **Step 4: Commit**

```bash
git add src/LlmsTxt/Endpoint.php src/Core/Plugin.php
git commit -m "feat(llms-txt): add rewrite endpoint and cache invalidation"
```

---

## Task 9: Robots\AiBotController — Block/Allow UI + Filter

**Files:**
- Create: `src/Robots/AiBotController.php`
- Create: `tests/Unit/Robots/AiBotControllerTest.php`

Supported bots (user-agent strings per official docs):
- `GPTBot` (OpenAI training)
- `OAI-SearchBot` (ChatGPT search)
- `ChatGPT-User` (ChatGPT browsing)
- `ClaudeBot` (Anthropic)
- `anthropic-ai` (older Anthropic bot)
- `Google-Extended` (Gemini training)
- `PerplexityBot` (Perplexity)
- `CCBot` (Common Crawl, feeds many)
- `Applebot-Extended` (Apple Intelligence)
- `Meta-ExternalAgent`
- `Bytespider` (ByteDance)

Default: all allowed (empty blocklist). Settings stores an array of blocked UA strings under option key `geoboost_blocked_ai_bots`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Robots/AiBotControllerTest.php`:
```php
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
}
```

- [ ] **Step 2: Run, confirm failure**

```bash
vendor/bin/phpunit --filter AiBotControllerTest
```

- [ ] **Step 3: Implement `src/Robots/AiBotController.php`**

```php
<?php
namespace GeoBoost\Robots;

use GeoBoost\Core\Settings;

final class AiBotController {

    private Settings $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function register(): void {
        add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 10, 2 );
    }

    /**
     * @return array<int,array{user_agent:string,label:string,purpose:string}>
     */
    public static function known_bots(): array {
        return [
            [ 'user_agent' => 'GPTBot',            'label' => 'OpenAI GPTBot',           'purpose' => 'Training data' ],
            [ 'user_agent' => 'OAI-SearchBot',     'label' => 'OpenAI SearchBot',        'purpose' => 'ChatGPT search' ],
            [ 'user_agent' => 'ChatGPT-User',      'label' => 'ChatGPT User',            'purpose' => 'ChatGPT browsing' ],
            [ 'user_agent' => 'ClaudeBot',         'label' => 'Anthropic ClaudeBot',     'purpose' => 'Training + Claude web search' ],
            [ 'user_agent' => 'anthropic-ai',      'label' => 'Anthropic (legacy)',      'purpose' => 'Legacy UA' ],
            [ 'user_agent' => 'Google-Extended',   'label' => 'Google Extended (Gemini)', 'purpose' => 'Gemini training' ],
            [ 'user_agent' => 'PerplexityBot',     'label' => 'Perplexity',              'purpose' => 'Perplexity answers' ],
            [ 'user_agent' => 'CCBot',             'label' => 'Common Crawl',            'purpose' => 'Dataset feeding many LLMs' ],
            [ 'user_agent' => 'Applebot-Extended', 'label' => 'Apple Intelligence',      'purpose' => 'Apple training' ],
            [ 'user_agent' => 'Meta-ExternalAgent','label' => 'Meta AI',                 'purpose' => 'Meta training' ],
            [ 'user_agent' => 'Bytespider',        'label' => 'ByteDance Bytespider',    'purpose' => 'ByteDance training' ],
        ];
    }

    /**
     * @param string|bool $public 1 if blog_public=1, 0 otherwise (WP passes as boolean-ish string).
     */
    public function filter_robots_txt( string $output, $public ): string {
        if ( ! $public ) {
            return $output;
        }

        $blocked = (array) $this->settings->get( 'blocked_ai_bots', [] );
        if ( empty( $blocked ) ) {
            return $output;
        }

        $known = array_column( self::known_bots(), 'user_agent' );
        $valid = array_values( array_intersect( $blocked, $known ) );

        if ( empty( $valid ) ) {
            return $output;
        }

        $append = "\n\n# GEOBoost: AI crawler blocks\n";
        foreach ( $valid as $ua ) {
            $append .= "User-agent: {$ua}\nDisallow: /\n\n";
        }

        return rtrim( $output, "\n" ) . $append;
    }
}
```

- [ ] **Step 4: Run, confirm pass**

```bash
vendor/bin/phpunit --filter AiBotControllerTest
```

Expected: 5 tests pass.

- [ ] **Step 5: Wire into Plugin bootstrap**

Update `src/Core/Plugin.php` `boot()`:
```php
public function boot(): void {
    $settings = new \GeoBoost\Core\Settings();

    ( new \GeoBoost\LlmsTxt\Endpoint(
        new \GeoBoost\LlmsTxt\Generator(),
        new \GeoBoost\LlmsTxt\Cache()
    ) )->register();

    ( new \GeoBoost\Robots\AiBotController( $settings ) )->register();
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Robots/AiBotController.php tests/Unit/Robots/AiBotControllerTest.php src/Core/Plugin.php
git commit -m "feat(robots): add AI bot blocker via robots_txt filter"
```

---

## Task 10: Admin\SettingsPage — Top-Level Admin Menu

**Files:**
- Create: `src/Admin/SettingsPage.php`
- Modify: `src/Core/Plugin.php`

Adds a top-level admin menu "GEOBoost" with a single "General" tab for Phase 1. Uses the WP Settings API to register fields. Fields in Phase 1:

1. **Site description** (textarea) — overrides auto-tagline in `/llms.txt`
2. **AI bots to block** (multi-checkbox) — writes to `geoboost_blocked_ai_bots`
3. **Include posts in llms.txt** (checkbox) — toggle post listing
4. **Include pages in llms.txt** (checkbox)

- [ ] **Step 1: Implement `src/Admin/SettingsPage.php`**

```php
<?php
namespace GeoBoost\Admin;

use GeoBoost\Robots\AiBotController;

final class SettingsPage {

    private const SLUG       = 'geoboost';
    private const OPTION_GRP = 'geoboost_options';

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_menu(): void {
        add_menu_page(
            __( 'GEOBoost', 'geoboost' ),
            __( 'GEOBoost', 'geoboost' ),
            'manage_options',
            self::SLUG,
            [ $this, 'render' ],
            'dashicons-admin-site-alt3',
            81
        );
    }

    public function register_settings(): void {
        register_setting( self::OPTION_GRP, 'geoboost_description', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ] );
        register_setting( self::OPTION_GRP, 'geoboost_include_posts', [
            'type'              => 'boolean',
            'sanitize_callback' => static fn( $v ) => (bool) $v,
            'default'           => true,
        ] );
        register_setting( self::OPTION_GRP, 'geoboost_include_pages', [
            'type'              => 'boolean',
            'sanitize_callback' => static fn( $v ) => (bool) $v,
            'default'           => true,
        ] );
        register_setting( self::OPTION_GRP, 'geoboost_blocked_ai_bots', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_bots' ],
            'default'           => [],
        ] );

        add_settings_section( 'main', __( 'General', 'geoboost' ), '__return_false', self::SLUG );

        add_settings_field( 'geoboost_description', __( 'Site description', 'geoboost' ),
            [ $this, 'field_description' ], self::SLUG, 'main' );
        add_settings_field( 'geoboost_include_posts', __( 'Include posts in llms.txt', 'geoboost' ),
            [ $this, 'field_include_posts' ], self::SLUG, 'main' );
        add_settings_field( 'geoboost_include_pages', __( 'Include pages in llms.txt', 'geoboost' ),
            [ $this, 'field_include_pages' ], self::SLUG, 'main' );
        add_settings_field( 'geoboost_blocked_ai_bots', __( 'Block AI bots', 'geoboost' ),
            [ $this, 'field_blocked_bots' ], self::SLUG, 'main' );
    }

    /**
     * @param mixed $value
     * @return array<int,string>
     */
    public function sanitize_bots( $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $known = array_column( AiBotController::known_bots(), 'user_agent' );
        return array_values( array_intersect( $value, $known ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'GEOBoost', 'geoboost' ); ?></h1>
            <p>
                <?php
                printf(
                    /* translators: %s: URL */
                    esc_html__( 'Your llms.txt is served at %s', 'geoboost' ),
                    '<code>' . esc_html( home_url( '/llms.txt' ) ) . '</code>'
                );
                ?>
            </p>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GRP );
                do_settings_sections( self::SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function field_description(): void {
        $v = (string) get_option( 'geoboost_description', '' );
        printf(
            '<textarea name="geoboost_description" rows="4" cols="60" class="large-text">%s</textarea>',
            esc_textarea( $v )
        );
        echo '<p class="description">' . esc_html__( 'Overrides the tagline in /llms.txt. Use this to describe what your site is about in 1-3 sentences.', 'geoboost' ) . '</p>';
    }

    public function field_include_posts(): void {
        $v = (bool) get_option( 'geoboost_include_posts', true );
        printf(
            '<label><input type="checkbox" name="geoboost_include_posts" value="1" %s /> %s</label>',
            checked( $v, true, false ),
            esc_html__( 'Include blog posts', 'geoboost' )
        );
    }

    public function field_include_pages(): void {
        $v = (bool) get_option( 'geoboost_include_pages', true );
        printf(
            '<label><input type="checkbox" name="geoboost_include_pages" value="1" %s /> %s</label>',
            checked( $v, true, false ),
            esc_html__( 'Include pages', 'geoboost' )
        );
    }

    public function field_blocked_bots(): void {
        $blocked = (array) get_option( 'geoboost_blocked_ai_bots', [] );
        echo '<fieldset>';
        foreach ( AiBotController::known_bots() as $bot ) {
            printf(
                '<label><input type="checkbox" name="geoboost_blocked_ai_bots[]" value="%1$s" %2$s /> <strong>%3$s</strong> — %4$s</label><br>',
                esc_attr( $bot['user_agent'] ),
                checked( in_array( $bot['user_agent'], $blocked, true ), true, false ),
                esc_html( $bot['label'] ),
                esc_html( $bot['purpose'] )
            );
        }
        echo '</fieldset>';
        echo '<p class="description">' . esc_html__( 'Selected bots will be added to robots.txt with Disallow: / directives.', 'geoboost' ) . '</p>';
    }
}
```

- [ ] **Step 2: Wire into Plugin bootstrap**

Update `src/Core/Plugin.php` `boot()`:
```php
public function boot(): void {
    $settings = new \GeoBoost\Core\Settings();

    ( new \GeoBoost\LlmsTxt\Endpoint(
        new \GeoBoost\LlmsTxt\Generator(),
        new \GeoBoost\LlmsTxt\Cache()
    ) )->register();

    ( new \GeoBoost\Robots\AiBotController( $settings ) )->register();

    if ( is_admin() ) {
        ( new \GeoBoost\Admin\SettingsPage() )->register();
    }
}
```

- [ ] **Step 3: Update Endpoint to respect include_posts/include_pages**

Edit `src/LlmsTxt/Endpoint.php` — replace the `post_type` line in `$query_args`:
```php
$post_types = [];
if ( get_option( 'geoboost_include_posts', true ) ) {
    $post_types[] = 'post';
}
if ( get_option( 'geoboost_include_pages', true ) ) {
    $post_types[] = 'page';
}

$query_args = [
    'post_type'      => $post_types ?: [ 'post' ],
    'post_status'    => 'publish',
    'posts_per_page' => 50,
    'orderby'        => 'date',
    'order'          => 'DESC',
];
```

And honor the description override in `$site`:
```php
$description_override = (string) get_option( 'geoboost_description', '' );
$site = [
    'site_title'  => get_bloginfo( 'name' ),
    'tagline'     => get_bloginfo( 'description' ),
    'description' => '' !== $description_override ? $description_override : '',
    'home_url'    => home_url( '/' ),
];
```

- [ ] **Step 4: Lint check**

```bash
vendor/bin/phpcs src/Admin/SettingsPage.php src/LlmsTxt/Endpoint.php
```

Expected: clean. Fix any WPCS issues surfaced.

- [ ] **Step 5: Commit**

```bash
git add src/Admin/SettingsPage.php src/Core/Plugin.php src/LlmsTxt/Endpoint.php
git commit -m "feat(admin): add settings page for Phase 1 options"
```

---

## Task 11: readme.txt (WordPress.org)

**Files:**
- Create: `readme.txt`

WP.org uses `readme.txt` (not Markdown) with a specific format. Writing now even though submission is Phase 4 — keeps it in sync as we add features.

- [ ] **Step 1: Create `readme.txt`**

```
=== GEOBoost ===
Contributors: jeangalea
Tags: seo, ai, llm, chatgpt, schema, llms.txt
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GEO (Generative Engine Optimization) for WordPress. Make your site AI-citable.

== Description ==

GEOBoost helps your WordPress site get cited by AI search engines — ChatGPT, Claude, Gemini, and Perplexity — when users ask relevant questions.

Designed to run alongside any SEO plugin (Rank Math, Yoast, SureRank, All in One SEO, SEOPress). GEOBoost does not replace your SEO plugin; it adds a GEO-specific layer on top.

Features in this version:

* **llms.txt generation** — auto-creates `/llms.txt` at your site root per the llmstxt.org proposal
* **AI bot controls** — one-click allow/disallow for GPTBot, ClaudeBot, Google-Extended, PerplexityBot, and 7 more
* **Settings page** — simple admin UI, no configuration required to start

More features are on the way: structured data optimized for LLMs, Gutenberg blocks for summaries and key facts, author expertise signals, and a GEO health dashboard.

== Installation ==

1. Upload `geoboost` to `/wp-content/plugins/`, or install via Plugins > Add New.
2. Activate through the Plugins screen.
3. Visit Settings → GEOBoost to configure.
4. Your `llms.txt` is served at `your-site.com/llms.txt` immediately.

== Frequently Asked Questions ==

= Does this replace my SEO plugin? =

No. GEOBoost is additive. It runs alongside Rank Math, Yoast, AIOSEO, SureRank, or SEOPress without conflict.

= What is llms.txt? =

An emerging proposal (llmstxt.org) for a file that tells LLM-powered tools what your site is about and which pages matter most.

= Does blocking AI bots affect my Google rankings? =

No. Google's regular crawler (Googlebot) is separate from Google-Extended (Gemini training). Blocking Google-Extended does not affect Google Search.

== Changelog ==

= 0.1.0 =
* Initial release
* llms.txt generator and endpoint
* AI bot allow/block controls via robots.txt
* Admin settings page
```

- [ ] **Step 2: Commit**

```bash
git add readme.txt
git commit -m "docs: add WP.org readme.txt"
```

---

## Task 12: Manual Smoke Test on Local WP

**No files created.** This task verifies the plugin works end-to-end on a real WP install. Jean already manages WP sites; this uses a local install (wp-env, Local, or LocalWP).

- [ ] **Step 1: Set up a local WordPress instance**

Option A — `wp-env` (official):
```bash
cd ~/Library/CloudStorage/Dropbox/Work/WordPress/geoboost
npm init -y
npm install --save-dev @wordpress/env
npx wp-env start
```

Expected: WP available at `http://localhost:8888`, admin at `http://localhost:8888/wp-admin` (admin/password).

Option B — if Jean prefers Local/LocalWP, symlink the plugin into `wp-content/plugins/geoboost`.

- [ ] **Step 2: Activate the plugin**

In WP admin → Plugins → activate "GEOBoost".

Expected: no PHP warnings. GEOBoost menu appears in admin sidebar.

- [ ] **Step 3: Visit `/llms.txt`**

```bash
curl -i http://localhost:8888/llms.txt
```

Expected:
- HTTP 200
- `Content-Type: text/plain; charset=utf-8`
- Body starts with `# ` and site title
- Contains `## Posts` section with the WP default "Hello world!" post

If 404: go to Settings → Permalinks and click Save (re-flushes rewrites).

- [ ] **Step 4: Test AI bot blocking**

- Go to GEOBoost settings → check `GPTBot` → Save
- Visit `http://localhost:8888/robots.txt`
- Expected: contains `User-agent: GPTBot` and `Disallow: /`

- [ ] **Step 5: Test cache invalidation**

- Create a new post, publish
- Re-fetch `/llms.txt`
- Expected: new post appears at top of `## Posts`

- [ ] **Step 6: Test with Rank Math installed alongside**

- Install Rank Math (free)
- Re-verify steps 3–5 still pass
- Expected: no fatal errors, no conflict

- [ ] **Step 7: Run full test suite**

```bash
vendor/bin/phpunit
vendor/bin/phpcs
```

Expected: all green.

- [ ] **Step 8: Commit any fixes found during smoke test**

```bash
git add -A
git commit -m "fix: smoke test findings"   # only if fixes were needed
```

---

## Task 13: Tag v0.1.0

- [ ] **Step 1: Update version strings**

Confirm `geoboost.php` header says `Version: 0.1.0` and `readme.txt` says `Stable tag: 0.1.0`. Already in sync from earlier tasks — just verify.

- [ ] **Step 2: Tag release**

```bash
git tag -a v0.1.0 -m "Phase 1 release: llms.txt + AI bot controls"
```

(Do not push yet — public GitHub repo is set up in Task 14.)

---

## Task 14: Public GitHub Repo

**Note:** Per Jean's AI hygiene rules: no CLAUDE.md, no SPEC.md, no .gitignore referencing AI tools, no "Co-Authored-By: Claude" in commits, no emoji in README feature lists. The `docs/` folder in the working directory (specs + plans) stays **local-only** and is NOT pushed.

- [ ] **Step 1: Create a minimal public-facing README.md**

```markdown
# GEOBoost

GEO (Generative Engine Optimization) for WordPress. Make your site AI-citable.

## Features

- `llms.txt` auto-generation served at `/llms.txt`
- AI bot allow/block controls (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, and more)
- Runs alongside any SEO plugin — Yoast, Rank Math, AIOSEO, SureRank, SEOPress

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

Install from the WordPress plugin directory, or clone this repo into `wp-content/plugins/geoboost` and run `composer install --no-dev`.

## Development

```
composer install
vendor/bin/phpunit
vendor/bin/phpcs
```

## License

GPL v2 or later.
```

- [ ] **Step 2: Verify no sensitive/AI-tooling files will be pushed**

```bash
git ls-files | grep -iE 'claude|\.cursor|superpowers|spec|prd|^docs/' || echo "clean"
```

Expected: `clean`. If anything appears, add to `.gitignore` and `git rm --cached` those paths first. The `docs/` folder in this project contains specs/plans — keep local-only:

```bash
echo "/docs/" >> .gitignore
git rm -r --cached docs/ 2>/dev/null || true
git add .gitignore
git commit -m "chore: keep docs/ local-only"
```

- [ ] **Step 3: Create public repo via gh**

```bash
gh repo create geoboost --public --source=. --description "GEO (Generative Engine Optimization) plugin for WordPress"
git push -u origin main
git push origin v0.1.0
```

- [ ] **Step 4: Double-check pushed contents**

```bash
gh repo view --web
```

Manually verify no personal paths, no `/docs/`, no AI-tooling references.

---

## Phase 1 Complete

You now have:
- Working plugin at `~/Library/CloudStorage/Dropbox/Work/WordPress/geoboost/`
- Public GitHub repo
- `v0.1.0` tag
- Passing test suite
- Ready to install on jeangalea.com for live dogfooding

**Next plans (separate files, written after Phase 1 is validated in production):**
- Phase 2: Schema output + SEO plugin detection
- Phase 3: Gutenberg blocks (summary, key facts, FAQ)
- Phase 4: Author signals + GEO health dashboard + WP.org submission
