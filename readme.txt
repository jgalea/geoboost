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
