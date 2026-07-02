# ZYMARG Search Engine — Latest

**Current version: `2.6.4`**

**⬇️ Download:** [ZYMARG_Search_Engine_v2.6.4.zip](https://github.com/Aspeyash/Search-engine/raw/refs/heads/main/ZYMARG_Search_Engine_v2.6.4.zip)

Install/update: WP Admin → Plugins → Add New → Upload Plugin → choose the zip → *Replace current with uploaded*. The inner folder slug is stable (`zymarg-search-engine/`), so upgrades update in place (no "Cannot redeclare class" duplicates).

## Recent versions

| Version | Notes |
|---------|-------|
| 2.6.4   | Zero-quota dropdown: suggests product/category/vendor names from a local list as you type (0 Algolia ops); Algolia only queried on the results page. ~90% dropdown cost cut. Toggle in Smart Features, ON by default, reversible. |
| 2.6.3   | Brand: WP admin now follows the ZYMARG brand design — branded settings header (Discovery Spark + wordmark left, version badge right), purple sidebar label, brand palette on the settings page + Dashboard analytics widget. Admin styling only. |
| 2.6.2   | Updated the Discovery Spark™ loading motion (fast sequential pulse). |
| 2.6.1   | Stable install folder slug (`zymarg-search-engine`) to stop duplicate-plugin fatals. |
| 2.6.0   | Tabbed settings card layout. |
| 2.5.0   | Top-level "Search Engine" menu + white-labeled admin. |

## How to find the latest version
Four independent pointers, kept in sync on every release:
1. This `LATEST.md`
2. The `VERSION` file (single line)
3. The plugin header `Version:` in `zymarg-search-engine/zymarg-algolia-search.php`
4. GitHub Releases (`/releases/latest`) once published

> Note: this repo's historical default branch was `feat/zymarg-algolia-search`.
> Releases now live on `main`.
