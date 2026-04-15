# GEO Plugin for WordPress — Free Version Design

**Date:** 2026-04-15
**Status:** Draft for review
**Scope:** Free / open-source version only. Pro version (citation measurement) is separate.

## Purpose

Optimize WordPress sites so they are more likely to be **cited** by AI search engines (ChatGPT, Claude, Gemini, Perplexity) when users ask relevant questions. Sits alongside existing SEO plugins (Rank Math, Yoast, SureRank) — does not replace them.

Target sites: jeangalea.com, wpmayor.com, spotlightwp.com, wprssaggregator.com (dogfooding).

## Positioning

- **Category:** GEO (Generative Engine Optimization) — a new, undefended niche
- **Principle:** do one thing well (like WP Rocket for caching, Redirection for redirects)
- **Compatibility:** never fights the SEO plugin — defers schema if the SEO plugin already handles it, adds GEO-specific layers on top
- **Name:** GEOBoost
- **WP.org slug:** `geoboost` (confirmed available 2026-04-15)

## What the plugin does (free tier)

### 1. `llms.txt` generator
Auto-generates and serves `/llms.txt` at site root.
- Summary of site, author, purpose
- Curated list of top pages/posts with one-line descriptions
- Optional include/exclude rules (by post type, category, tag)
- Auto-updates on post publish/update
- Based on the emerging llms.txt proposal (llmstxt.org)

### 2. Enhanced structured data for LLMs
Adds/augments schema.org markup optimized for LLM ingestion:
- `Article` with full `author`, `datePublished`, `dateModified`, `about`, `mentions`
- `FAQPage` auto-detected from H2/H3 question patterns ("How do I…", "What is…")
- `HowTo` auto-detected from numbered step lists
- `Person`/`Organization` for author pages
- Defers if SEO plugin already outputs the same schema type (detects and skips)

### 3. Content summary block (Gutenberg)
A block authors can drop at top of long posts. Generates a short, fact-dense summary (5 bullet points or 3 sentences) that LLMs preferentially ingest. Two modes:
- **Manual:** author writes it
- **AI-assisted:** uses local WP options (not external APIs) OR integrates with existing AI plugins via hooks. Free tier stays API-free.

### 4. Key facts / TL;DR block (Gutenberg)
Structured key-value block for claims, stats, definitions. Renders as `<dl>` with schema markup. LLMs love dense, structured facts.

### 5. FAQ block (Gutenberg)
Proper FAQ block with schema.org FAQPage output. Many SEO plugins have this; ours differs by being GEO-optimized (question phrasing suggestions, answer length recommendations).

### 6. Author/expertise signals
Enhanced author schema: credentials, expertise areas, social profiles, publication history. Critical for LLM trust signals — LLMs weight authority heavily. Pulls from WP user profile + adds GEO-specific fields.

### 7. robots.txt AI bot controls
UI to allow/disallow specific AI crawlers (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot). Default: allow all. One-click disallow. Shows current status.

### 8. GEO health dashboard
Admin page showing GEO readiness per post:
- Has summary? ✓/✗
- Has structured data? ✓/✗
- Has author schema? ✓/✗
- Word count in healthy range? ✓/✗
- Clear H1/H2 hierarchy? ✓/✗
- Simple grade (A-F) per post

## Architecture

### Plugin structure
```
geo-wp/
├── geo-wp.php              # Main plugin file, bootstrap, activation
├── src/
│   ├── LlmsTxt/            # llms.txt generation + serving
│   ├── Schema/             # Schema.org output, SEO plugin conflict detection
│   ├── Blocks/             # Gutenberg blocks (summary, key-facts, faq)
│   ├── Author/             # Author expertise signals
│   ├── Robots/             # AI bot robots.txt controls
│   ├── Dashboard/          # Admin health dashboard
│   └── Core/               # Plugin bootstrap, settings, utilities
├── assets/
│   ├── js/                 # Block editor scripts (built)
│   └── css/
├── blocks/                 # Block source (src → build)
├── languages/              # i18n .pot file
├── readme.txt              # WP.org readme
├── package.json            # Block build via @wordpress/scripts
└── composer.json           # Autoload, dev deps (phpunit, phpcs)
```

### Design principles
- **PSR-4 autoloading** under `GeoWp\` namespace
- **Zero external runtime dependencies** — no Composer runtime packages, no external HTTP calls in free tier
- **WordPress coding standards** (phpcs/WPCS)
- **Block editor native** — Gutenberg blocks, not shortcodes
- **Hooks for extensibility** — every output filterable
- **PHP 7.4+, WP 6.0+** (covers >90% of WP sites)
- **No telemetry** in free tier

### Data model
- **Plugin options** (`wp_options`): settings only
- **Post meta**: per-post GEO data (summary, key facts, GEO grade cache)
- **No custom tables** — keep it clean, removable, fast

### Conflict handling with SEO plugins
Detects Rank Math / Yoast / SureRank / AIOSEO / SEOPress via plugin slugs or class existence. For schema output, checks whether the SEO plugin already emits that schema type for the current page — if yes, skips. Filterable so advanced users can override.

## What we're NOT building in free tier (YAGNI)

- No LLM API calls (measurement = Pro)
- No automated content rewriting
- No AI-generated summaries (stays API-free; hooks for Pro/third-party)
- No sitemap (SEO plugins handle this)
- No redirect management
- No social card generation
- No page speed / Core Web Vitals
- No analytics integration
- No multisite-specific features (MVP: single site)
- No RSS/feed modifications
- No WooCommerce-specific schema (later expansion)

## Success criteria (MVP)

1. Installs cleanly on all 4 Jean sites without breaking existing SEO plugins
2. Generates valid `/llms.txt` auto-updating on content changes
3. Outputs schema that validates in Google Rich Results Test and schema.org validator
4. All 3 Gutenberg blocks work in block editor
5. Admin dashboard shows per-post GEO grade
6. Passes WordPress.org plugin review (security, coding standards, no prohibited practices)
7. Zero PHP errors/warnings on PHP 7.4, 8.0, 8.1, 8.2, 8.3 with WP 6.0–6.7

## Distribution strategy (free)

- Submit to WordPress.org plugin directory
- Public GitHub repo (no CLAUDE.md, no AI-tooling files per Jean's AI hygiene rules)
- Install on Jean's 4 sites for real-world validation + case studies
- Feed install/user data insights into WP Mayor editorial

## Locked decisions

1. **Name:** GEOBoost
2. **WP.org slug:** `geoboost`
3. **License:** GPL v2 or later
4. **Author:** Jean Galea
5. **Support channels:** WP.org support forums + GitHub issues
6. **Minimum PHP:** 7.4
7. **Minimum WordPress:** 6.0
8. **Namespace:** `GeoBoost\`
9. **Text domain:** `geoboost`

## Next steps after approval

1. User reviews this spec
2. Invoke `writing-plans` skill to produce phased implementation plan
3. Build Phase 1 (bootstrap + llms.txt + robots controls — simplest, validates plumbing)
4. Build Phase 2 (schema output + SEO plugin detection)
5. Build Phase 3 (Gutenberg blocks)
6. Build Phase 4 (dashboard + polish)
7. Dogfood on jeangalea.com first, then the other three
8. Submit to WordPress.org
