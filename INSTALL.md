# ZYMARG Algolia Search - Install Guide

A complete, drop-in WordPress plugin that gives **zymarg.com** Algolia-powered instant search for products, vendors, and categories.

## What you get

- WordPress plugin: `zymarg-algolia-search/`
- Indexes WooCommerce products, product categories, Dokan vendor profiles
- Auto-updates the Algolia index on save/update/delete (background, won't block admin)
- Brand-styled instant search bar (white base, soft purple radial orbs, ZYMARG purple accents)
- Custom no-results dropdown -> "Couldn't find what you're looking for?" + Request Here button -> `/community`
- Bengali + English tokenization
- SEO-safe (form submit still hits `?s=...&post_type=product` so Google crawls real result pages)
- Mobile responsive
- No conflict with WooCommerce, Dokan, Astra, or Elementor Pro

---

## Step 1 — Create your Algolia account

1. Go to <https://www.algolia.com/users/sign_up> and sign up (free tier covers up to 10K records / 10K searches per month — fine for launch).
2. Create a new application. Pick the region closest to Bangladesh (e.g. **Singapore (ap-southeast-1)** or **Mumbai (ap-south-1)**) for lowest latency.
3. Open **Settings -> API Keys**. You will need three values:
   - **Application ID** (e.g. `XXXXXXXXXX`)
   - **Admin API Key** (write access — keep secret)
   - **Search-Only API Key** (public — used in the browser)

---

## Step 2 — Install the plugin

### Option A: Upload via WordPress admin (easiest)

1. Download the plugin zip: see the link in the chat after I push the branch.
2. In WP Admin, go to **Plugins -> Add New -> Upload Plugin**.
3. Choose the zip and click **Install Now**, then **Activate**.

### Option B: Upload via Hostinger File Manager / FTP

1. Unzip the file locally — you'll get a `zymarg-algolia-search/` folder.
2. Upload the folder to `/wp-content/plugins/` on your Hostinger server.
3. In WP Admin, go to **Plugins** and activate **ZYMARG Algolia Search**.

---

## Step 3 — Configure

1. Go to **Settings -> ZYMARG Algolia**.
2. Paste:
   - Application ID
   - Admin API Key
   - Search-Only API Key
3. Leave **Index prefix** as `zymarg_` (creates `zymarg_products`, `zymarg_vendors`, `zymarg_categories`).
4. Tick both **English** and **Bengali** under Languages.
5. Confirm **Community Request Board URL** is `https://zymarg.com/community` (or whatever your community page is).
6. Click **Save settings**.
7. Click **Verify connection** — should say "Algolia connection OK."
8. Click **Reindex everything now** — this queues all your existing products / vendors / categories. Indexing happens in the background via Action Scheduler (or WP-Cron fallback). Big stores can take a few minutes.

---

## Step 4 — Place the search bar on the site

### In Astra header (any builder)

Use the shortcode anywhere — text widget, custom HTML block, header builder slot:

```
[zymarg_algolia_search]
```

Optional: custom placeholder

```
[zymarg_algolia_search placeholder="Search ZYMARG..."]
```

### In Elementor Pro

1. Edit the header template.
2. Drop in a **Shortcode** widget.
3. Paste `[zymarg_algolia_search]`.
4. Save.

### In Astra Theme Builder Header

1. **Appearance -> Customize -> Header Builder** (or **Astra -> Header Builder** on Astra Pro).
2. Drag an **HTML** element into your header row.
3. Set its content to: `[zymarg_algolia_search]`
4. Publish.

---

## Step 5 — Verify it works

1. Open zymarg.com (incognito).
2. Type a product name in the search bar.
3. The dropdown should appear instantly with sections: **Categories**, **Products**, **Vendors**.
4. Type random nonsense (e.g. `xyz123`) — you should see the friendly box:
   - "Couldn't find what you're looking for?"
   - **Request Here** button → `/community`
5. Clear the search or click outside — dropdown closes automatically.

---

## How auto-indexing works

- **Add / edit / publish a product** -> indexed on shutdown (non-blocking).
- **Trash / delete a product** -> removed from index.
- **Stock change** -> re-indexed.
- **Vendor signs up via Dokan** -> indexed.
- **Vendor edits store profile** -> re-indexed.
- **Vendor disabled / deleted** -> removed from index.
- **Add / edit / delete product category** -> updated.

The plugin uses **Action Scheduler** (bundled with WooCommerce) when available, so writes don't block the admin save action. If Action Scheduler is unavailable it falls back to running on `shutdown`, after the response is sent.

---

## SEO

- The search **form action** still points to your homepage with `?s=...&post_type=product`, so Google can crawl real WooCommerce search results.
- The instant dropdown is purely a UX layer for typing.
- No JS-only routes — all "See all results" links are real, crawlable URLs.

---

## Troubleshooting

**Nothing appears in the dropdown:**
- Confirm you ran "Reindex everything now" and waited for it to finish.
- Open the browser console (F12). Any `[ZymargAlgolia]` errors?
- Confirm the Search-Only API Key is set (not blank).

**Indexing seems stuck:**
- Make sure WP-Cron is firing (visit `https://zymarg.com/wp-cron.php` once).
- Or, on Hostinger, set up a real cron hitting `wp-cron.php` every 5 minutes.

**Bengali results not matching:**
- Make sure both `en` and `bn` are ticked in **Settings -> ZYMARG Algolia -> Languages**, then click **Reindex everything now** again to apply settings.

**Theme conflict:**
- The plugin doesn't override any WordPress filters by default — it adds a self-contained widget. If your theme injects a different search form into the header, replace it with the shortcode.

---

## Support

Edit this plugin to your taste. Code lives in:
- `zymarg-algolia-search/zymarg-algolia-search.php` — bootstrap
- `zymarg-algolia-search/includes/` — PHP classes
- `zymarg-algolia-search/assets/css/zymarg-search.css` — brand styles
- `zymarg-algolia-search/assets/js/zymarg-search.js` — instant search renderer
