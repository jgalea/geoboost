=== GEOBoost ===
Contributors: jeangalea
Tags: ai-seo, generative-engine-optimization, llms-txt, schema, chatgpt
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See what stops AI search from reading your site, which AI crawlers visit, and give ChatGPT, Claude and Gemini what they need to cite you.

== Description ==

GEOBoost scans your site the way AI crawlers see it and tells you what's stopping ChatGPT, Claude, Gemini and Perplexity from reading or citing it, with a fix for each problem. Then it tracks whether those crawlers actually visit and whether AI answers send you traffic.

GEOBoost runs alongside Yoast SEO, Rank Math, All in One SEO, SEOPress, SureRank or The SEO Framework without conflict. Your SEO plugin covers traditional search; GEOBoost covers what AI search needs on top: crawler access, readable content, summaries, key facts, author signals and llms.txt.

**What GEOBoost does**

* **AI visibility scan**: reads your robots.txt rules for each AI crawler, requests your page as GPTBot, ClaudeBot and PerplexityBot to catch firewalls and security plugins that turn them away (including challenge or login pages served with a 200), and checks noindex/noai tags, text that only appears with JavaScript, headings, FAQ answers, alt text, schema, contact markup, Open Graph, your sitemap and llms.txt. You get a 0 to 100 score and a fix for every problem.
* **AI crawl and referral tracking**: logs AI bot visits and clicks from AI answers to a dedicated table, so you can see which AI engines crawl your site and which send traffic.
* **llms.txt generation**: serves an auto-generated `/llms.txt` at your site root following the llmstxt.org proposal, with your site description, key pages, posts, pages and authors.
* **AI-ready schema output**: emits Article, Person, Organization and WebSite + SearchAction JSON-LD. It detects your SEO plugin and skips schema types it already outputs, so nothing is duplicated.
* **Blocks for AI citability**: Summary (TL;DR), Key Facts and FAQ (with FAQPage schema), with shortcode fallbacks for the classic editor.
* **GEO Score**: a 0 to 100 score per post with history, tier grading and recommendations in the post sidebar.
* **Author E-E-A-T**: expertise, credentials and qualifications on user profiles, output as Person schema on author archives.
* **AI bot controls**: one-click Disallow in robots.txt for GPTBot, ClaudeBot, Google-Extended, PerplexityBot and more, with a warning when you block a crawler that AI search uses.
* **GEO dashboard**: the site scan, average score and distribution, 30 days of bot activity, AI referral clicks with 30-day changes, top posts, recommendations and site health.
* **Custom JSON-LD per post**: a meta box to add JSON-LD your SEO plugin doesn't handle.

**Who it's for**

Content-driven sites, blogs, publishers, B2B marketing sites, local businesses, and anyone who wants to be the source AI cites when their topic comes up.

== Installation ==

1. Upload `geoboost` to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate through the Plugins screen.
3. Visit GEOBoost → Settings to configure.
4. Your `llms.txt` is served at `your-site.com/llms.txt` immediately.

Installing from the GitHub repository (for developers):

1. Clone the repo into `wp-content/plugins/geoboost`.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Activate via the WP admin.

The WordPress.org zip bundles the autoloader, so this step only applies to git checkouts.

== Frequently Asked Questions ==

= Does this replace my SEO plugin? =

No. GEOBoost is additive. It runs alongside Yoast, Rank Math, AIOSEO, SEOPress, SureRank, and The SEO Framework without conflict. Where your SEO plugin already outputs a schema type, GEOBoost defers.

= What is llms.txt? =

An emerging proposal (llmstxt.org) for a file at your site root that tells AI engines what your site is about, which pages matter most, and who the authors are. Think of it as robots.txt for LLMs.

= Does blocking AI bots affect my Google rankings? =

No. Google's regular crawler (Googlebot) is separate from Google-Extended (Gemini training). Blocking Google-Extended does not affect Google Search indexing or rankings.

= Why is my GEO score clustered around 70–75? =

Most posts lack a Summary block, Key Facts block, or FAQ signals — each worth 10 points. Add those three blocks to a high-value post to push it into the 90s.

= Where does GEOBoost store data? =

A dedicated `{prefix}_geoboost_events` table for bot crawl and referral logs. Post scores and signals are stored as post meta, author fields as user meta, and settings as options. Deleting the plugin removes all of it, including the table and the scheduled cleanup task, on every site of a multisite network.

= Does GEOBoost send my data anywhere? =

No. GEOBoost only requests your own site: the AI visibility scan (including as GPTBot, ClaudeBot and PerplexityBot) and a check that nothing else is serving /llms.txt. No third-party services are contacted.

== Changelog ==

= 0.4.1 =
* Fix: /llms.txt now works right after activation, without re-saving permalinks.
* Fix: no more "headers already sent" warnings on /llms.txt with WP_DEBUG on.
* The site scan stops after 25 seconds so it can't hit the PHP time limit. Checks it didn't reach show as "Not run" and are left out of the score.
* Custom JSON-LD is re-encoded on save and output so it can't break out of its script tag. Invalid JSON is still rejected.
* The GEO score no longer runs other plugins' content filters when it analyzes a post.
* Request data used for crawl and referral logging, and author expertise terms, is now sanitized.
* Uninstall now removes all post meta, user meta, transients, options, the events table and the scheduled task, on every site in a network.
* Translatable error messages and block text domains.
* Requires WordPress 6.3 or later. Tested up to 7.1.

= 0.4.0 =
* New AI visibility scan on the dashboard. It fetches your home page, or any URL on your site, from your public address the way a crawler does, and scores it 0-100 across crawler access, readable content, structured data and discovery files.
* The scan checks robots.txt per AI crawler (search crawlers fail if blocked, training crawlers only warn), requests the page as GPTBot, ClaudeBot and PerplexityBot to catch firewall blocks, and looks for noindex/noai, readable text, headings, FAQ signals, alt text, lang, Organization schema with sameAs, contact links, Open Graph, canonical, XML sitemap and a valid llms.txt.
* AI bot settings now mark which crawlers feed live AI answers and warn when you block one of them.
* The scan's own requests are not logged as AI bot crawls.

= 0.3.0 =
* New dashboard: KPI cards (average score, bot crawls + sparkline, AI referrals, top crawler) with vs-prior-30d deltas; segmented score distribution; site health card; top posts with Last scanned column; actionable recommendation cards with deep-linked filtered post lists.
* New per-post ScoreBox design with a score ring and tier-specific notes.
* Redesigned Settings page with card layout and segmented schema-mode controls.
* PostListFilter: recommendation cards deep-link into a filtered post list with a clear-filter notice.
* Internal: DataProvider consolidates dashboard queries with a 60s transient cache; Svg helpers replace the legacy SvgChart.
* Tests: unit coverage for Svg helpers and PostListFilter signal mapping.

= 0.2.1 =
* Security hardening: referer URLs are now truncated to scheme+host before logging.
* Security hardening: dashboard page now requires manage_options capability.
* Security hardening: stricter URL sanitization on author profile fields.
* Docs: added developer install instructions to readme.

= 0.2.0 =
* JSON-LD schema output (Article, Person, Organization, WebSite) with SEO plugin conflict avoidance (Yoast, Rank Math, AIOSEO, SEOPress, SureRank).
* Per-post Custom JSON-LD meta box.
* Three Gutenberg blocks: FAQ (with FAQPage schema), Summary (TL;DR), Key Facts.
* Shortcode fallbacks for classic editor.
* Content Analyzer and per-post GEO Score (0–100) with history.
* Author E-E-A-T profile fields + Person schema on author archives.
* AI bot crawl log + AI referral tracking in new geoboost_events table.
* Dashboard with score distribution, top posts, bot activity, referral activity, and aggregated recommendations.
* llms.txt enhancements: Key Pages (from top-scoring posts), Authors section, per-post override.
* Admin notice if an external /llms.txt overrides our endpoint.
* WCAG 2.1 AA accessibility improvements.

= 0.1.0 =
* Initial release.
* llms.txt generator and endpoint.
* AI bot allow/block controls via robots.txt.
* Admin settings page.

== Accessibility ==

GEOBoost aims for WCAG 2.1 AA in admin and frontend output. Dashboard charts include role and aria-label attributes; FAQs use native `<details>`/`<summary>`; all admin controls are keyboard navigable; color is never the only conveyor of meaning (tier pills use both color and number).
