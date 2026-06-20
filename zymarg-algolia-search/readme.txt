=== ZYMARG Algolia Search ===
Contributors: zymarg
Tags: search, algolia, woocommerce, dokan, instantsearch, multivendor
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.1.0
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

= 2.1.0 =
* NEW: Orphaned-record cleanup. Removes index entries for products that were deleted, trashed, or unpublished but were never removed from Algolia. These leftovers accumulated (e.g. 1,162 indexed records vs 1,007 published products) and stacked at the top of the "Latest" (date_created desc) virtual replica, making the search-results page slow (~8s) and showing fewer than a full page of products until you scrolled past them.
* Runs automatically with every "Reindex everything now", plus a new "Remove orphaned records" button in Settings for an on-demand cleanup without a full reindex.
* Out-of-stock products are preserved (they remain published, so they are never treated as orphans).
* Because the sort indexes are virtual replicas of zymarg_products, cleaning the primary cleans every sort replica automatically.
* Added client methods: browse_object_ids() and delete_objects() (batch).

= 2.0.2 =
* CHANGE: Out-of-stock products are intentionally KEPT in the search index so they remain visible in search results, regardless of WooCommerce's global "Hide out of stock items" setting. Only catalog-visibility "hidden" and unpublished products are excluded. (Reverts the 2.0.1 visibility gate, which could have hidden out-of-stock products from search.)
* Kept: reindex now removes products that are no longer indexable (hidden/unpublished) so stale records don't linger.
* Note: the "Empty card response" error on the search-results "Latest" sort is resolved by the Search Result Page plugin v1.0.1 (it skips pages whose products cannot be rendered). This plugin keeps out-of-stock products searchable.
* A `zymarg_algolia_index_product` filter is available to customise what gets indexed.

= 2.0.1 =
* FIX: Products that WooCommerce hides (out of stock with "Hide out of stock items" enabled, or catalog-visibility "hidden") are no longer indexed, and are removed from the index on reindex and on stock-status change. Previously these were indexed but the product grid refused to render them, causing an "Empty card response" error on the search-results page — most visibly under the "Latest" sort (e.g. a broad query like "A" where the newest products were out of stock).
* The indexer now mirrors the grid's visibility rule exactly, so Algolia never returns a product ID the grid cannot display. A `zymarg_algolia_index_product` filter is available to override this.
* NOTE: run Settings -> ZYMARG Algolia -> "Reindex everything now" once after updating so existing out-of-stock/hidden products are purged from the index.

= 2.0.0 =
* NEW: "Smart Features" on/off switches in Settings -> ZYMARG Algolia. Each smart feature can now be turned off individually, with a plain-language note next to every switch explaining what it does.
* Toggles added for: Recent searches, Keyboard navigation, Click tracking (Algolia Insights), "Did you mean" related results, and the Result count badge. (The existing Trending searches switch stays in the No-results CTA section.)
* Safe by design: every switch defaults to ON, so behavior is identical to 1.0.36 until you change something. The frontend treats any missing flag as ON, so cached config can never silently disable a feature.
* No changes to the dropdown design, animation, Elementor/Gutenberg widgets, or the search-results page handoff.

= 1.0.29 =
* Performance: zymarg-search.js now registers with `strategy=>'defer'` on WordPress 6.3+, falling back to plain in-footer on older versions. The script already guards its own init with a readyState check + DOMContentLoaded listener, and retries config detection via setTimeout, so defer is fully safe. Saves ~50–100ms on Time to Interactive on most pages.



= 1.0.22 =
* **Fix:** "Sort by" bar now appears only when the user presses Enter while there is an active search query. Previously it was always visible below the search bar. Pressing Enter with a typed query keeps the dropdown open and reveals the sort bar; closing the dropdown (Esc, click outside, or Clear) hides it again. If the input is empty, Enter still submits the form as normal.
* **Fix:** "Sort by" bar pills row is now horizontally centred (`justify-content: center`) on all screen sizes.

= 1.0.20 =
* **Sort filter row** — a slim row of "Sort by" pills now appears between the search input and the results dropdown.
  * Four sort options: Price Low to High, Price High to Low, Latest, Oldest.
  * Mutual exclusion enforced: selecting one price direction deselects the other; same for Latest/Oldest. Cross-group combinations (e.g. "Price Low to High + Latest") are allowed.
  * Fully responsive: single scrollable line on mobile, comfortable spacing on tablet and desktop.
  * Fully customisable: every label, the row prefix text, and the pill order are editable in Settings → ZYMARG Algolia → Sort Filter Row.
  * Sort is applied client-side on returned Algolia hits (products only) — no Algolia replica required.
  * Toggle the entire row on/off with the "Show sort bar" checkbox.

= 1.0.18 =
* **Indexer: 7 new / refreshed product fields** pushed to the Algolia products index. Supports merchandising widgets (e.g. Product Archive Grid v1.1.0) that read directly from Algolia, plus better faceting and ranking for instant search.
  * `regular_price` (float) — list price before any discount.
  * `sale_price` (float, nullable) — current sale price; `null` when the product isn't on sale.
  * `stock_quantity` (int, nullable) — current managed stock count; `null` when stock isn't being managed (or for parent-only variable products).
  * `total_sales` (int) — now sourced from `wp_wc_product_meta_lookup.total_sales` (kept in sync by Woo) with a graceful fall-back to the legacy `total_sales` postmeta. Faster reads on bulk reindex; identical output otherwise.
  * `product_type` (string) — `simple` / `variable` / `grouped` / `external`. Lets downstream widgets route variable products to the product page instead of trying to AJAX-add them.
  * `min_variation_price` (float, nullable) — minimum visible variation price for variable products; `null` for non-variables. Read from `_min_price` postmeta (kept in sync by `WC_Product_Variable::sync()`).
  * `max_variation_price` (float, nullable) — maximum visible variation price; `null` for non-variables. Read from `_max_price` postmeta.
* **Backwards compatible.** Existing fields (`price`, `on_sale`, `in_stock`, `categories`, `vendor_*`, etc.) are unchanged. The new fields appear on each product the next time the indexer runs — trigger a manual reindex from **Settings → ZYMARG Algolia → Reindex everything now** after upgrading.
* **No public-facing JS / CSS / search-behavior changes** in this release. Bumped JS internal `VERSION` constant to `1.0.18` so the console banner accurately reports the running version.

= 1.0.17 =
* **New: Loading spinner mode setting** — pick when (or if) the small purple spinner appears inside the search dropdown. Three options:
  * **While searching (default)** — current behavior, spinner shows only during the brief moment Algolia is fetching results.
  * **On focus** — spinner shows the moment the user clicks/taps the search bar, hides when the dropdown closes (or when results render).
  * **Always hidden** — spinner never appears, regardless of state.
* The setting is per-instance (different ZYMARG Search widgets on the same site can have different spinner modes), available in:
  * Elementor widget -> Content tab -> "Loading spinner" select
  * Gutenberg block sidebar -> "Content" panel -> "Loading spinner" select
  * Appearance -> Widgets -> ZYMARG Search -> "Loading spinner" select
* **Internal CSS plumbing fix:** added `.zymarg-algolia-loading[hidden] { display: none !important; }` so the JS visibility control on the spinner element actually takes effect. Without this rule, our class selector's `display: flex` was tying with -- and shadowing -- the browser's `[hidden]{display:none}` user-agent rule, making "Always hidden" mode (and the brief hide between API calls) impossible. One-line CSS fix; no behavior change for the default mode.
* **No public-facing JS or CSS changes** beyond the new spinner mode handling. Search performance is unchanged.

= 1.0.16 =
* **New: Search analytics dashboard upgrade** — six new admin-only metrics in the WP Dashboard widget, all built on top of Algolia's existing Analytics API. Zero impact on public site speed or search performance (admin-only code, 30-min cached, never loaded on the frontend).
* **Stat cards row at top of widget:**
  * **Searches (7d)** with inline SVG sparkline showing daily volume trend
  * **Click-through rate** (% of searches that led to a click) with click count / search count breakdown
  * **Avg click position** (lower = more relevant top results)
  * **No-click queries** count
* **New table: "Searches With No Clicks (Last 7 Days)"** — searches that returned hits but nobody clicked. Far more actionable than the zero-results table because it pinpoints where titles, photos, or prices need work.
* **New chart: "Click Position Distribution"** — inline SVG bar chart showing which result slot (1, 2, 3, ...) gets the most clicks. Most clicks at position 1-3 = top results are highly relevant. Mostly position 4+ = ranking needs work.
* **New: "Health check" expandable panel** — purely local checks (no API calls): App ID configured, Admin/Search keys configured, each index has records (warns when empty), CTA mode in use, analytics region setting. Spot misconfigurations at a glance.
* **Internal:** new `fetch_analytics_json()` helper for Algolia Analytics endpoints with non-search-shaped JSON responses; new `get_health_checks()` and `render_sparkline()` methods. All three endpoints (no-clicks, click-through-rate, avg-click-position, click-positions, search-volume-count) reuse the same multi-region detection from v1.0.14 — no extra region probing.
* **Backwards compatible:** existing dashboard layout unchanged; new sections appear above and below; cache key bumped to `_v2` to force a fresh fetch on first install.
* **Zero changes to public-facing JS / CSS / search behavior.**

= 1.0.15 =
* **New: Result count badge** — every dropdown now shows the total number of matches at the top (e.g., "231 results") plus per-section counts (Products (12), Categories (3)). Frames the response so users know there's more to explore.
* **New: Recent searches** — when the user focuses the empty input, their last 5 unique search queries appear as clickable pills. Stored entirely in the user's `localStorage` (per-browser, never sent to your server, GDPR-clean). Includes a small "Clear" link to wipe history.
* **New: Trending searches** — pulls the top searches from your existing analytics cache and shows them as pills below recent searches when the input is empty. Auto-refreshes every 30 minutes. The list will be empty for ~24h after install while Algolia processes enough searches to populate it.
* **New: "Showing related results for X"** — when an exact-match search returns zero hits, the plugin automatically retries once with `removeWordsIfNoResults: 'allOptional'` (Algolia's word-relaxation parameter). If that finds related products, they're shown with a clear header instead of the empty CTA. Catches typos and over-specific queries gracefully.
* **New: Click tracking via Algolia Insights** — every search now includes `clickAnalytics: true` and every result click fires an asynchronous event to `https://insights.algolia.io/1/events`. Uses `navigator.sendBeacon` for reliability across page navigations. Each user gets an anonymous UUID stored in `localStorage` (no PII, no cookies). After 7+ days of click data, Algolia's machine-learning **Re-Ranking** feature will start automatically promoting popular results. Verify event ingestion in your Algolia dashboard at **Events → Debugger** after install.
* **New: Keyboard navigation** — ↑/↓ to move between results, Enter to open the highlighted one, Esc to close. The active hit is visually highlighted with a soft purple background. Form-submit (Enter when no hit is highlighted) still goes to `/?s=` as before, so both UX patterns coexist correctly.
* **Internal:** new `Zymarg_Algolia_Dashboard::get_cached_trending_searches()` static helper that reads the analytics cache without making any live API calls — adds zero latency to every public pageview.

= 1.0.14 =
* **Fix:** Dashboard analytics widget now finds your data when your Algolia cluster is in the EU region (Germany, France, UK, etc). Algolia segregates analytics by region — apps on EU clusters are served from `analytics.de.algolia.com`, not the global `analytics.algolia.com`. The plugin previously only queried the global endpoint, which silently returns HTTP 200 with an empty `searches` array for EU apps. Now the dashboard tries both endpoints automatically (Global first, EU fallback) and locks onto whichever returns data.
* **New:** "Analytics region" setting in **Settings → ZYMARG Algolia → Search behavior** with three options: Auto-detect (default), Global / US, EU / Germany / UK. Most users should leave it on Auto-detect.
* **New:** Diagnostic footer at the bottom of the dashboard widget showing which Algolia analytics region was used, the last fetch time, and any API error message — so future analytics issues are obvious instead of silent.
* **No JS changes** in this release.

= 1.0.13 =
* **Typography:** plugin now uses **Cabinet Grotesk** for headings (section titles in dropdown, "Couldn't find" message, banner heading) and **Inter** for body text (input, hit titles, prices, buttons, links). Both are referenced **by name only** — no `@font-face`, no Google Fonts request, zero external HTTP. Your theme is responsible for loading the actual font files; if it doesn't, the plugin falls back to Inter, then to system fonts (`system-ui`, `-apple-system`, `BlinkMacSystemFont`, `'Segoe UI'`, `Roboto`).
* **New CSS variables:** `--zymarg-font-heading` and `--zymarg-font-body`. Defined on both `.zymarg-algolia-wrapper` and `.zymarg-algolia-cta-banner`. Override in custom CSS to swap fonts per site without modifying the plugin.
* **Improvement:** input field font-family now uses `var(--zymarg-font-body) !important` in the high-specificity rule, so Astra/Elementor/theme rules targeting `input[type="search"]` can no longer override the body font.
* **Bumped JS internal VERSION constant** from 1.0.7 to 1.0.13 so the console banner accurately reports the running version.

= 1.0.12 =
* **New:** Global "CTA Mode" setting in Settings -> ZYMARG Algolia with three options:
  * **Show in dropdown** — current behavior; the "Couldn't find / Request Here" CTA appears inside the search dropdown when zero results match.
  * **Show on the search results page** — auto-injects a banner below the WP search results page (`/?s=keyword`) that **always shows** regardless of whether any products matched. The dropdown CTA is automatically hidden in this mode so the user only sees one CTA at a time.
  * **Hidden everywhere** — completely disabled.
* **New:** Banner styling controls (only used in "search-results-page" mode): max width (px), vertical / horizontal padding (px), margin top / bottom (px), border radius (px), alignment (left / center / right), message text size (px), button text size (px), banner background, message text color, button background color, button text color. All edited in the Settings page with number inputs + native color pickers.
* **New:** `[zymarg_search_cta]` shortcode — manually place the banner anywhere (useful for Elementor Pro custom search templates / Astra Theme Builder pages where the auto-inject hooks can't reach).
* **New CSS variables:** `--zymarg-cta-max-width`, `--zymarg-cta-padding-y`, `--zymarg-cta-padding-x`, `--zymarg-cta-margin-top`, `--zymarg-cta-margin-bottom`, `--zymarg-cta-radius`, `--zymarg-cta-text-size`, `--zymarg-cta-btn-size`, `--zymarg-cta-bg`, `--zymarg-cta-text`, `--zymarg-cta-btn-bg`, `--zymarg-cta-btn-color`, `--zymarg-cta-align`. Power users can override these via custom CSS.
* **Auto-inject mechanism:** the banner attaches to two hooks (`loop_end` after the main search loop, `astra_content_bottom` as Astra-specific fallback). A render flag prevents double output if both fire.
* **Backward compat:** existing installs default to `cta_mode = 'dropdown'`, so behavior is unchanged until the user opts into the new mode.

= 1.0.11 =
* **New:** Section-level toggles in Elementor + Gutenberg block + classic widget. Three independent on/off switches: "Show Products section", "Show Categories section", "Show Vendors section". Defaults: Products + Categories ON, Vendors OFF.
* **Changed:** Render order in the dropdown is now **Products → Categories → Vendors** (was Categories → Products → Vendors). Products show first because that's what users are looking for.
* **Improvement:** When a section toggle is OFF, the plugin **skips the Algolia API call** for that index entirely. Reduces query volume by ~33% and prevents the "Index does not exist" error when an index hasn't been auto-created yet (e.g. no Dokan vendors yet → no `zymarg_vendors` index → previously broke instant search; now silently skipped).
* **Removed:** "Text line height" control in Elementor + "Line height (×10)" range in Gutenberg block. `<input>` elements ignore line-height visibly when they have a fixed bar height, so the slider had no visible effect — confusing. Use Bar height + Vertical text padding instead for size control.
* **Backward compatible:** existing widget instances without these new settings inherit the defaults (Products + Categories ON, Vendors OFF). Existing instances with `lineHeight` set silently ignore it.

= 1.0.10 =
* **Removed:** "Full screen width (break out of parent)" toggle. The control + CSS class + render handling are gone everywhere (Elementor, Gutenberg, classic widget).
* **New:** Full "Clear button (×)" customization section. In Elementor (Style tab) and Gutenberg block (sidebar):
  * Show clear button toggle (default ON)
  * Position: Right / Left (default Right)
  * Button size slider (12–70 px) — small to big
  * Icon size slider (6–40 px) — the X inside the button
  * Border radius slider (0 → 50% — square to circle)
  * Space between X and input text (0–40 px)
  * Distance from edge (0–40 px)
  * Background color (default + hover)
  * Icon color (default + hover)
* **New CSS variables:** `--zymarg-clear-size`, `--zymarg-clear-icon-size`, `--zymarg-clear-radius`, `--zymarg-clear-gap`, `--zymarg-clear-edge`, `--zymarg-clear-bg`, `--zymarg-clear-color`, `--zymarg-clear-bg-hover`, `--zymarg-clear-color-hover`.
* **New CSS modifiers:** `.zymarg-no-clear` (hide button) and `.zymarg-clear-left` (move to left side).
* **HTML change:** the SVG inside the clear button no longer has hard-coded `width="14" height="14"` — sizing is purely CSS-controlled via `--zymarg-clear-icon-size` so the icon size slider actually takes effect.

= 1.0.9 =
* **Fix:** Input-field controls (Text size / Text weight / Vertical text padding / Text line height) now actually apply on the page. The previous version had the right CSS variables but Astra / Elementor's global `input[type="search"]` rules were silently overriding them due to equal-specificity cascade order. v1.0.9 uses higher-specificity selectors plus `!important` for these critical user-controlled style props so theme CSS can no longer beat them.
* **New:** "Show empty message" toggle in Elementor + Gutenberg + classic widget. Turn it OFF and the "Couldn't find what you're looking for? Request Here" CTA is hidden — when zero results match, the dropdown closes silently instead of showing the CTA.
* **New:** "Message text size" + "Button text size" controls for the empty state in both Elementor (sliders) and Gutenberg block (range controls). All other empty-state controls (text color, button bg, hover bg, button text, button radius) are now hidden in Elementor when the message is toggled off.
* **New CSS variable:** `--zymarg-empty-btn-size` (default 14px) — wire up the new button text size control.

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
