=== ZYMARG Algolia Search ===
Contributors: zymarg
Tags: search, algolia, woocommerce, dokan, instantsearch, multivendor
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.14
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
