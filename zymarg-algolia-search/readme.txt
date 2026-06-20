=== ZYMARG Algolia Search ===
Contributors: zymarg
Tags: search, algolia, woocommerce, dokan, instantsearch, multivendor
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Algolia-powered instant search for the ZYMARG marketplace. Indexes WooCommerce products, product categories and Dokan vendors. Brand-styled instant dropdown with custom no-results CTA linking to the Community Request Board.

== Description ==

This plugin connects the ZYMARG marketplace (WooCommerce + Dokan + Astra + Elementor Pro) to Algolia and renders a brand-styled instant search bar with a custom dropdown.

Features:

* Indexes WooCommerce products, product categories, Dokan vendor profiles
* Auto re-indexes on save / update / delete (background, non-blocking)
* Bengali (bn) + English (en) language tokenization
* Multi-index instant dropdown (products / vendors / categories)
* Typo tolerance, synonyms, custom ranking
* Brand-styled (white base, soft purple radial orbs, ZYMARG purple accents)
* Custom no-results dropdown with "Request Here" button -> /community
* SEO-safe: form submit still goes to ?s= so Google can crawl
* Mobile responsive, no iOS zoom-on-focus
* No conflict with WooCommerce / Dokan / Astra / Elementor Pro
* Lightweight: ~12kb CSS + ~6kb JS, libraries loaded from jsDelivr CDN

== Installation ==

1. Upload the plugin zip to **Plugins -> Add New -> Upload Plugin**.
2. Activate.
3. Go to **Settings -> ZYMARG Algolia** and paste your Algolia App ID, Admin API Key, and Search-Only API Key.
4. Click **Verify connection**, then **Reindex everything now**.
5. Place the search bar with the shortcode `[zymarg_algolia_search]` (works in Elementor, Astra header, widgets, anywhere).

== Changelog ==

= 2.0.0 =
* NEW: "Search Engine 2.0" smart features, each with its own on/off toggle in Settings and a full reference note in the WP Dashboard widget.
* NEW: Keyboard navigation in the dropdown (Up/Down to move, Enter to open) using the existing highlight style.
* NEW: Recent searches — shows a user's last few queries on focus (stored privately in their own browser).
* NEW: Query Suggestions — optional as-you-type suggestions from an Algolia Query Suggestions index.
* NEW: Algolia Insights (opt-in) — sends click events so search ranking improves over time.
* NEW: No-results logging — captures searches that returned nothing, viewable in the Dashboard widget (works on the free tier without relying on Algolia analytics retention).
* PERF: Request-sequence guard + in-memory cache prevent stale result flashes and make repeat searches instant.
* PERF: Removed the unused InstantSearch.js library from the frontend (smaller, faster page loads).
* The dropdown animation and the SEO-safe ?s= results-page handoff are unchanged.

= 1.0.0 =
* Initial release.
