=== ZYMARG Algolia Search ===
Contributors: zymarg
Tags: search, algolia, woocommerce, dokan, instantsearch, multivendor
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Algolia-powered instant search for the ZYMARG marketplace. Indexes WooCommerce products, product categories and Dokan vendors. Brand-styled instant dropdown with custom no-results CTA linking to the Community Request Board. Drag-and-drop block + Elementor widget — no shortcode required.

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
* Lightweight: ~12kb CSS + ~6kb JS, libraries loaded from jsDelivr CDN

== Installation ==

1. Upload the plugin zip to **Plugins -> Add New -> Upload Plugin**.
2. Activate.
3. Go to **Settings -> ZYMARG Algolia** and paste your Algolia App ID, Admin API Key, and Search-Only API Key.
4. Click **Verify connection**, then **Reindex everything now**.
5. Place the search bar — pick whichever you prefer:
   * **Elementor:** drag *"ZYMARG Search"* from the panel (under the *ZYMARG* category). Live preview in the editor.
   * **Gutenberg / Site Editor:** click the inserter, search *"ZYMARG Search"*.
   * **Appearance -> Widgets:** drop the *"ZYMARG Search"* widget into any sidebar or header widget zone.
   * *(Legacy)* shortcode `[zymarg_algolia_search]` still works.

== Changelog ==

= 1.0.5 =
* **New:** "ZYMARG Search" Gutenberg block — drag from the inserter, no shortcode needed.
* **New:** Elementor widget under the *ZYMARG* category, with live editor preview, alignment, max-width, accent/border/background color controls, input height and border radius.
* **New:** Classic WP_Widget for legacy widget areas (Astra header widget zones, sidebars, footers).
* **Fix:** Instant search now fires as you type instead of only on Enter (was caused by an unused `window.instantsearch` boot guard that silently aborted the script when the unrelated InstantSearch.js library failed to load).
* **Fix:** Search bar now renders live inside the Elementor editor — no need to publish to see it.
* **Fix:** Empty-state dropdown ("Couldn't find what you're looking for? Request Here") now reliably appears when the JS boots.
* **Fix:** Race-protect concurrent requests so a slow earlier query never overwrites a newer one.
* **Improvement:** Removed the unused `instantsearch.js` script (saves ~30kb on every page).
* **Improvement:** Boot now uses DOMContentLoaded + library polling + MutationObserver, so it works on cached pages, in the block editor preview, and inside the Elementor preview iframe.

= 1.0.0 =
* Initial release.
