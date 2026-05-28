=== ZYMARG Algolia Search ===
Contributors: zymarg
Tags: search, algolia, woocommerce, dokan, instantsearch, multivendor
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Algolia-powered instant search for the ZYMARG marketplace. Indexes WooCommerce products, product categories and Dokan vendors. Brand-styled instant dropdown with custom no-results CTA. Drag-and-drop block + Elementor widget — no shortcode required.

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
* Three placement options — drag the Gutenberg block, the Elementor widget, or the classic widget. Shortcode kept for backwards compatibility.
* Lightweight: ~14 KB CSS + ~10 KB JS, **zero external libraries** — talks to Algolia's REST API directly via fetch()
* Every dimension and color is a CSS variable, so the Elementor widget exposes ~30 controls and the Gutenberg block has full sidebar controls

== Installation ==

1. Upload the plugin zip to **Plugins -> Add New -> Upload Plugin**.
2. Activate.
3. Go to **Settings -> ZYMARG Algolia** and paste your Algolia App ID, Admin API Key, and Search-Only API Key.
4. Click **Verify connection**, then **Reindex everything now**.
5. Place the search bar — pick whichever you prefer:
   * **Elementor:** drag *"ZYMARG Search"* from the panel (under the *ZYMARG* category). Live preview in the editor with full styling controls.
   * **Gutenberg / Site Editor:** click the inserter, search *"ZYMARG Search"*. Adjust layout / colors in the right sidebar.
   * **Appearance -> Widgets:** drop the *"ZYMARG Search"* widget into any sidebar or header widget zone.
   * *(Legacy)* shortcode `[zymarg_algolia_search]` still works.

== Changelog ==

= 1.0.6 =
* **Critical fix:** instant search no longer depends on any external CDN. The script now calls Algolia's REST API directly with `fetch()`, with multi-host failover (`-dsn` -> `-1` -> `-2` -> `-3`). This eliminates the entire class of "search only fires on Enter" bugs caused by jsDelivr being blocked, slow, or cached as a stale failure by ad-blockers, WAFs, or strict CSP.
* **New:** Comprehensive Elementor controls — bar height, max width (px / % / vw), font size, font weight, letter spacing, horizontal padding, icon size + gap, border radius, border width, all colors (text / placeholder / background / border / accent), dropdown max height (px or vh), dropdown background / border / radius / offset, drop-shadow style, full empty-state customization (text color, button bg / hover / text / radius).
* **New:** Gutenberg block sidebar now offers Range controls for max width, bar height, text size, padding, icon size, border radius, dropdown max height, dropdown radius, dropdown offset, plus a color panel for text / placeholder / background / border / accent / dropdown background.
* **New:** Every dimension and color is now a wrapper-scoped CSS variable, so per-instance customization works cleanly without leaking between widgets.
* **Improvement:** Search bar now stretches to the full container width when "Max width" is increased — was previously capped because the wrapper wasn't using `width: 100%` on flex parents.
* **Improvement:** Built-in v1.0.6 console banner (`[ZymargAlgolia] v1.0.6 ready`) so you can verify in DevTools that the new script is actually running.

= 1.0.5 =
* New "ZYMARG Search" Gutenberg block, Elementor widget (under the ZYMARG category), and classic WP_Widget. No more shortcode required.
* Fixed instant search firing only on Enter (caused by an unused `window.instantsearch` boot guard).
* Search bar now renders live inside the Elementor editor — no need to publish.
* Race-protect concurrent Algolia requests.

= 1.0.0 =
* Initial release.
