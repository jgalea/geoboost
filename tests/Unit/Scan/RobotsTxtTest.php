<?php
namespace GeoBoost\Tests\Unit\Scan;

use GeoBoost\Scan\RobotsTxt;
use PHPUnit\Framework\TestCase;

final class RobotsTxtTest extends TestCase {

	public function test_empty_file_allows_everything(): void {
		$this->assertTrue( ( new RobotsTxt( '' ) )->is_allowed( 'GPTBot', '/' ) );
	}

	public function test_wildcard_group_applies_when_no_specific_group(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /private/\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/private/page' ) );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/public/' ) );
	}

	public function test_specific_group_replaces_wildcard_group(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nAllow: /\n" );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/anything' ) );
		$this->assertFalse( $r->is_allowed( 'ClaudeBot', '/anything' ) );
	}

	public function test_specific_group_without_rules_allows_everything(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /\n\nUser-agent: PerplexityBot\nDisallow:\n" );
		$this->assertTrue( $r->is_allowed( 'PerplexityBot', '/' ) );
	}

	public function test_agent_match_is_case_insensitive(): void {
		$r = new RobotsTxt( "user-agent: gptbot\ndisallow: /\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/' ) );
	}

	public function test_agent_tokens_must_match_exactly(): void {
		$r = new RobotsTxt( "User-agent: Google\nDisallow: /\n" );
		$this->assertTrue( $r->is_allowed( 'Google-Extended', '/' ) );
	}

	public function test_consecutive_user_agent_lines_share_a_group(): void {
		$r = new RobotsTxt( "User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/' ) );
		$this->assertFalse( $r->is_allowed( 'CCBot', '/' ) );
		$this->assertTrue( $r->is_allowed( 'ClaudeBot', '/' ) );
	}

	public function test_user_agent_after_rules_starts_a_new_group(): void {
		$r = new RobotsTxt( "User-agent: GPTBot\nDisallow: /\nUser-agent: CCBot\nDisallow: /tmp/\n" );
		$this->assertTrue( $r->is_allowed( 'CCBot', '/' ) );
		$this->assertFalse( $r->is_allowed( 'CCBot', '/tmp/x' ) );
	}

	public function test_groups_for_the_same_agent_are_merged(): void {
		$r = new RobotsTxt( "User-agent: GPTBot\nDisallow: /a/\n\nUser-agent: GPTBot\nDisallow: /b/\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/a/1' ) );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/b/1' ) );
	}

	public function test_longest_matching_rule_wins(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /blog/\nAllow: /blog/public/\n" );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/blog/public/post' ) );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/blog/draft' ) );
	}

	public function test_allow_wins_a_tie(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /page\nAllow: /page\n" );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/page' ) );
	}

	public function test_wildcard_and_end_anchor(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /*.pdf$\nDisallow: /*?s=\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/files/guide.pdf' ) );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/files/guide.pdf?download=1' ) );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/?s=query' ) );
	}

	public function test_regex_characters_in_paths_are_literal(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /a.b\n" );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/axb' ) );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/a.b' ) );
	}

	public function test_comments_and_crlf_are_ignored(): void {
		$r = new RobotsTxt( "# hello\r\nUser-agent: GPTBot # training\r\nDisallow: / # all\r\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/' ) );
	}

	public function test_wordpress_default_robots_allows_search_crawlers(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: https://example.com/wp-sitemap.xml\n" );
		$this->assertTrue( $r->is_allowed( 'OAI-SearchBot', '/' ) );
		$this->assertFalse( $r->is_allowed( 'OAI-SearchBot', '/wp-admin/options.php' ) );
		$this->assertTrue( $r->is_allowed( 'OAI-SearchBot', '/wp-admin/admin-ajax.php' ) );
	}

	public function test_sitemaps_are_collected(): void {
		$r = new RobotsTxt( "Sitemap: https://example.com/a.xml\nUser-agent: *\nDisallow:\nsitemap: https://example.com/b.xml\nSitemap:\n" );
		$this->assertSame( array( 'https://example.com/a.xml', 'https://example.com/b.xml' ), $r->sitemaps() );
	}

	public function test_trailing_star_does_not_add_specificity(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /foo*\nAllow: /foo\n" );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/foo' ) );
	}

	public function test_hostile_pattern_without_match_finishes_fast(): void {
		$r     = new RobotsTxt( "User-agent: *\nDisallow: /" . str_repeat( '*a', 200 ) . "b\n" );
		$start = microtime( true );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/' . str_repeat( 'a', 5000 ) ) );
		$this->assertLessThan( 1.0, microtime( true ) - $start );
	}

	public function test_hostile_pattern_that_matches_is_not_lost_to_backtrack_limit(): void {
		$r     = new RobotsTxt( "User-agent: *\nDisallow: /" . str_repeat( '*a', 30 ) . "*b\n" );
		$start = microtime( true );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/' . str_repeat( 'a', 2000 ) . 'b' ) );
		$this->assertLessThan( 1.0, microtime( true ) - $start );
	}

	public function test_percent_escapes_of_unreserved_characters_are_normalized(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /%7Efoo\nDisallow: /~bar\nDisallow: /a%2fb\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/~foo' ) );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/%7ebar' ) );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/a%2Fb' ) );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/a/b' ) );
	}

	public function test_over_long_rules_are_skipped_and_flagged(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /" . str_repeat( 'x', 600 ) . "\n" );
		$this->assertTrue( $r->is_truncated() );
		$this->assertNull( $r->verdicts( array( 'GPTBot' ), '/' ) );
	}

	public function test_rules_past_the_cap_are_skipped_and_flagged(): void {
		$r = new RobotsTxt( "User-agent: *\n" . str_repeat( "Disallow: /filler\n", 2000 ) . "Disallow: /\n" );
		$this->assertTrue( $r->is_truncated() );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/page' ) );
		$this->assertFalse( ( new RobotsTxt( "User-agent: *\nDisallow: /\n" ) )->is_truncated() );
	}

	public function test_verdicts_give_up_when_the_step_budget_runs_out(): void {
		$text  = "User-agent: *\n" . str_repeat( 'Disallow: /' . str_repeat( '*a', 200 ) . "b\n", 600 );
		$r     = new RobotsTxt( $text );
		$start = microtime( true );
		$this->assertNull( $r->verdicts( array( 'GPTBot', 'CCBot' ), '/' . str_repeat( 'a', 5000 ) ) );
		$this->assertLessThan( 2.0, microtime( true ) - $start );
		$this->assertSame( array( 'GPTBot' => true ), ( new RobotsTxt( "User-agent: *\nDisallow: /x\n" ) )->verdicts( array( 'GPTBot' ), '/' ) );
	}

	public function test_query_string_is_part_of_the_match(): void {
		$r = new RobotsTxt( "User-agent: *\nDisallow: /*?lang=\n" );
		$this->assertFalse( $r->is_allowed( 'GPTBot', '/?lang=en' ) );
		$this->assertTrue( $r->is_allowed( 'GPTBot', '/' ) );
	}
}
