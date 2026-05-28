=== ZYMARG Algolia Search ===
Contributors: zymarg
Tags: search, algolia, woocommerce, dokan, instantsearch, multivendor
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Algolia-powered instant search for the ZYMARG marketplace. Brand-styled instant dropdown with full Elementor + Gutenberg styling controls. No external CDN.

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
* Three placement options — Gutenberg block, Elementor widget, or classic widget. Shortcode kept for backwards compatibility.
* Lightweight: ~14 KB CSS + ~12 KB JS, **zero external libraries** — talks to Algolia's REST API directly via fetch()
* Every dimension and color is a CSS variable, so the Elementor widget exposes ~35 controls and the Gutenberg block has full sidebar controls

== Installation ==

1. Upload the plugin zip to **Plugins -> Add New -> Upload Plugin**.
2. Activate.
3. Go to **Settings -> ZYMARG Algolia** and paste your Algolia App ID, Admin API Key, and Search-Only API Key.
4. Click **Verify connection**, then **Reindex everything now**.
5. Place the search bar — pick whichever you prefer:
   * **Elementor:** drag *"ZYMARG Search"* from the panel (under the *ZYMARG* category).
   * **Gutenberg / Site Editor:** click the inserter, search *"ZYMARG Search"*.
   * **Appearance -> Widgets:** drop the *"ZYMARG Search"* widget into any sidebar.

After installing, **clear your hosting page cache** (LiteSpeed / WP Rocket / W3 Total Cache / Cloudflare) and hard-reload your page (Ctrl/Cmd + Shift + R), otherwise the cached HTML will still reference the old script.

== Diagnostics ==

If instant search isn't firing, open your site in DevTools (F12) -> Console and run:

`zymargAlgoliaDebug()`

It returns a status object showing version, whether fetch is available, whether the config is on window, how many search wrappers are on the page, and the last error. Paste that output to support to diagnose any remaining issues.

== Changelog ==

= 1.0.8 =
* **New:** "Full screen width (break out of parent)" toggle in Elementor + Gutenberg block + classic widget. Spans the entire viewport regardless of how narrow the parent Elementor column / Astra section is, using the standard CSS breakout technique (`margin-left: calc(50% - 50vw); width: 100vw`). Use this when the Stretch toggle still feels limited because the parent container itself is constrained.
* **New:** "Show results dropdown" toggle. Turn it OFF and the live dropdown is completely hidden — the search bar then behaves like a plain WP search form (type, then press Enter to go to the search results page).
* **Improvement:** Max-width slider raised from 3000 px to 5000 px in both Elementor and Gutenberg.
* **No changes** to the instant search JS this round (per request).

= 1.0.7 =
* **New:** "Stretch to full container width" toggle in both Elementor widget and Gutenberg block — drops the max-width cap so the bar fills 100% of its container regardless of the slider.
* **New:** Max-width slider now goes up to **3000px** (was 1600px).
* **New:** "Input field (text area)" Elementor section with explicit Vertical text padding, Line height, Min width controls — fully addresses the "tab where I write the word" customization request.
* **Fix:** Defensive boot — leading semicolon in IIFE so JS combiners cannot break the script. Wrapper detection now falls back to `.zymarg-algolia-wrapper` class if `data-zymarg-search` is stripped by HTML minifiers. Multiple input event listeners (input / keyup / paste / change / compositionend) so IME, paste, autofill all trigger instant search.
* **Fix:** Config detection retries 8 times over ~2s in case wp_localize_script is delayed by JS deferring/combining plugins.
* **New diagnostic:** call `zymargAlgoliaDebug()` in DevTools to print a full status report (version, config, wrappers found/booted, last query, last error).
* **New:** Plugin version is now exposed via `window.ZymargAlgolia.version` so you can verify which version is actually loaded.

= 1.0.6 =
* Critical fix: instant search no longer depends on any external CDN. Direct fetch() to Algolia REST API with multi-host failover.
* Comprehensive Elementor + Gutenberg styling controls.
* Every dimension/color is now a wrapper-scoped CSS variable.

= 1.0.5 =
* New "ZYMARG Search" Gutenberg block, Elementor widget (under ZYMARG category), classic WP_Widget. No more shortcode required.
* Search bar now renders live inside the Elementor editor.

= 1.0.0 =
* Initial release.
