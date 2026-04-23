=== GEOBoost ===
Contributors: jeangalea
Tags: ai-seo, generative-engine-optimization, llms-txt, schema, chatgpt
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generative Engine Optimization for WordPress. Get cited by ChatGPT, Claude, Gemini, and Perplexity.

== Description ==

GEOBoost prepares your WordPress site to be cited by AI search engines and chatbots — ChatGPT, Claude, Gemini, Perplexity, and Microsoft Copilot — when users ask questions related to your content.

GEOBoost is additive. It runs alongside Yoast SEO, Rank Math, All in One SEO, SEOPress, SureRank, or The SEO Framework without conflict. Where your SEO plugin covers traditional search (Google, Bing), GEOBoost adds the layer AI search uses: structured summaries, key facts, author E-E-A-T signals, and the emerging llms.txt standard.

**What GEOBoost does**

* **llms.txt generation** — serves an auto-generated `/llms.txt` at your site root following the llmstxt.org proposal, with your site description, key pages (selected by GEO score), blog posts, pages, and authors.
* **AI-ready schema output** — emits Article, Person, Organization, and WebSite + SearchAction JSON-LD. Auto-detects your SEO plugin and skips schema types it already outputs to prevent duplicates.
* **Gutenberg blocks for AI citability** — Summary (TL;DR), Key Facts, and FAQ (with FAQPage schema). Shortcode fallbacks for the classic editor.
* **GEO Score** — per-post 0–100 score with a score history, tier grading, and per-post recommendations in the post sidebar.
* **Author E-E-A-T** — expertise, credentials, and qualifications added to user profiles; surfaced as Person schema on author archives.
* **AI bot controls** — one-click Disallow in robots.txt for GPTBot, ClaudeBot, Google-Extended, PerplexityBot, and more.
* **AI crawl and referral tracking** — logs bot visits and AI referral clicks to a dedicated table. See which AI engines are crawling your site and which are sending traffic.
* **GEO dashboard** — average score, score distribution, bot activity (30 days with a sparkline), AI referral clicks with 30-day deltas, top-performing posts, actionable recommendations, and site health.
* **Custom JSON-LD per post** — meta box to append arbitrary JSON-LD for structured data your SEO plugin doesn't handle.

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

A dedicated `{prefix}_geoboost_events` table for bot crawl and referral logs. Post scores and signals are stored as postmeta. Everything is removed on uninstall.

== Changelog ==

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
