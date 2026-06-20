/*!
 * ZYMARG Search Engine - Frontend instant search.
 * v1.0.36
 *
 * Changes from v1.0.32:
 *   1. Search-as-you-type progress bar — thin purple bar animates under the
 *      input wrap while a request is in-flight (.is-searching class on wrapper).
 *   6. Category scope filter — when data-category-scope="1" is set on the
 *      wrapper, a row of category pills appears above product results. Tapping
 *      a pill scopes all subsequent searches to that category; tap again to clear.
 *   7. Cross-device recent-search sync — when WooCommerce user is logged in,
 *      recent searches are read from and pushed to user_meta via AJAX.
 *      Guest users fall back to localStorage silently.
 */
;(function () {
	'use strict';

	var VERSION = '2.1.0';

	/* ---------------------------------------------------------------- */
	/* Local-storage helpers                                             */
	/* ---------------------------------------------------------------- */
	var STORAGE_RECENT = 'zymarg_recent_searches';
	var STORAGE_USER   = 'zymarg_user_token';
	var RECENT_LIMIT   = 5;

	function safeLocalGet(key) {
		try { return window.localStorage.getItem(key); } catch (e) { return null; }
	}
	function safeLocalSet(key, value) {
		try { window.localStorage.setItem(key, value); } catch (e) {}
	}
	function safeLocalRemove(key) {
		try { window.localStorage.removeItem(key); } catch (e) {}
	}

	function uuidv4() {
		var rnd = (window.crypto && typeof window.crypto.getRandomValues === 'function')
			? function (n) { var a = new Uint8Array(n); window.crypto.getRandomValues(a); return a; }
			: function (n) { var a = new Uint8Array(n); for (var i = 0; i < n; i++) a[i] = Math.floor(Math.random() * 256); return a; };
		var b = rnd(16);
		b[6] = (b[6] & 0x0f) | 0x40;
		b[8] = (b[8] & 0x3f) | 0x80;
		var hex = '';
		for (var i = 0; i < 16; i++) {
			hex += (b[i] < 16 ? '0' : '') + b[i].toString(16);
			if (i === 3 || i === 5 || i === 7 || i === 9) hex += '-';
		}
		return hex;
	}

	function getUserToken() {
		var t = safeLocalGet(STORAGE_USER);
		if (!t) { t = 'anonymous-' + uuidv4(); safeLocalSet(STORAGE_USER, t); }
		return t;
	}

	/* ---------------------------------------------------------------- */
	/* Recent searches — localStorage layer (always available)           */
	/* ---------------------------------------------------------------- */
	function getLocalRecent() {
		try {
			var raw = safeLocalGet(STORAGE_RECENT);
			if (!raw) return [];
			var arr = JSON.parse(raw);
			return Array.isArray(arr) ? arr : [];
		} catch (e) { return []; }
	}

	function setLocalRecent(list) {
		try { safeLocalSet(STORAGE_RECENT, JSON.stringify(list)); } catch (e) {}
	}

	function addLocalRecent(q) {
		q = (q || '').trim();
		if (!q) return;
		var list = getLocalRecent().filter(function (s) {
			return s && String(s).toLowerCase() !== q.toLowerCase();
		});
		list.unshift(q);
		if (list.length > RECENT_LIMIT) list = list.slice(0, RECENT_LIMIT);
		setLocalRecent(list);
	}

	function clearLocalRecent() { safeLocalRemove(STORAGE_RECENT); }

	/* ---------------------------------------------------------------- */
	/* Cross-device sync (Feature 7) — AJAX to user_meta                */
	/* Only active when cfg.syncEnabled === 1 (logged-in WC user).      */
	/* Writes to localStorage as well so the UI is always instant.       */
	/* ---------------------------------------------------------------- */
	var syncCache = null; // in-memory cache so we don't re-fetch on every focus

	function syncGetRecent(cfg, callback) {
		if (!cfg.syncEnabled || !cfg.syncAjaxUrl || !cfg.syncNonce) {
			callback(getLocalRecent());
			return;
		}
		// Return cached value immediately, refresh in background.
		if (syncCache !== null) {
			callback(syncCache);
			return;
		}
		var fd = new FormData();
		fd.append('action', 'zymarg_get_searches');
		fd.append('nonce',  cfg.syncNonce);
		fetch(cfg.syncAjaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success && Array.isArray(res.data.searches)) {
					syncCache = res.data.searches;
					// Merge with any localStorage terms collected while offline.
					var local = getLocalRecent();
					local.forEach(function (t) {
						if (!syncCache.some(function (s) { return s.toLowerCase() === t.toLowerCase(); })) {
							syncCache.unshift(t);
						}
					});
					syncCache = syncCache.slice(0, RECENT_LIMIT);
					setLocalRecent(syncCache);
				} else {
					syncCache = getLocalRecent();
				}
				callback(syncCache);
			})
			.catch(function () {
				syncCache = getLocalRecent();
				callback(syncCache);
			});
	}

	function syncPushRecent(cfg, term) {
		// Always write locally first for instant UI.
		addLocalRecent(term);
		if (syncCache !== null) {
			var lower = term.toLowerCase();
			syncCache = syncCache.filter(function (s) { return s.toLowerCase() !== lower; });
			syncCache.unshift(term);
			syncCache = syncCache.slice(0, RECENT_LIMIT);
		}
		if (!cfg.syncEnabled || !cfg.syncAjaxUrl || !cfg.syncNonce) return;
		var fd = new FormData();
		fd.append('action', 'zymarg_push_searches');
		fd.append('nonce',  cfg.syncNonce);
		fd.append('term',   term);
		fetch(cfg.syncAjaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success && Array.isArray(res.data.searches)) {
					syncCache = res.data.searches;
					setLocalRecent(syncCache);
				}
			})
			.catch(function () {});
	}

	function syncClearRecent(cfg) {
		clearLocalRecent();
		syncCache = [];
		// No server-side clear endpoint needed — next push will rebuild the list.
	}

	/* ---------------------------------------------------------------- */
	/* Algolia Insights                                                  */
	/* ---------------------------------------------------------------- */
	function sendInsightsEvent(cfg, indexName, queryID, objectID, position) {
		if (!cfg || !cfg.appId || !cfg.searchKey) return;
		if (!queryID || !objectID || !indexName) return;
		var payload = {
			events: [{
				eventType: 'click',
				eventName: 'Product Clicked from Search',
				index:     indexName,
				queryID:   queryID,
				objectIDs: [String(objectID)],
				positions: position ? [position] : undefined,
				userToken: getUserToken(),
				timestamp: Date.now()
			}]
		};
		try {
			var body = JSON.stringify(payload);
			if (navigator && typeof navigator.sendBeacon === 'function') {
				var url = 'https://insights.algolia.io/1/events' +
					'?X-Algolia-Application-Id=' + encodeURIComponent(cfg.appId) +
					'&X-Algolia-API-Key=' + encodeURIComponent(cfg.searchKey);
				navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
				return;
			}
			fetch('https://insights.algolia.io/1/events', {
				method: 'POST',
				headers: {
					'X-Algolia-Application-Id': cfg.appId,
					'X-Algolia-API-Key':        cfg.searchKey,
					'Content-Type':             'application/json'
				},
				body: body, keepalive: true, mode: 'cors', credentials: 'omit'
			}).catch(function () {});
		} catch (e) {}
	}

	/* ---------------------------------------------------------------- */
	/* Diagnostics                                                       */
	/* ---------------------------------------------------------------- */
	var diag = {
		version: VERSION, fetchAvailable: typeof window.fetch === 'function',
		mutationObserver: typeof window.MutationObserver === 'function',
		configFound: false, configValid: false,
		wrappersFound: 0, wrappersBooted: 0,
		lastQuery: null, lastResultCounts: null, lastError: null
	};

	window.zymargAlgoliaDebug = function () {
		var ws = document.querySelectorAll('[data-zymarg-search], .zymarg-algolia-wrapper');
		diag.wrappersFound = ws.length;
		diag.wrappersBooted = 0;
		Array.prototype.forEach.call(ws, function (w) { if (w.__zymargBooted) diag.wrappersBooted++; });
		diag.configFound = !!window.ZymargAlgolia;
		diag.configValid = !!(window.ZymargAlgolia && window.ZymargAlgolia.appId && window.ZymargAlgolia.searchKey);
		try {
			console.group('[ZymargAlgolia] diagnostics');
			console.log('version:', diag.version);
			console.log('config:', window.ZymargAlgolia || '(missing!)');
			console.log('wrappers found:', diag.wrappersFound, '| booted:', diag.wrappersBooted);
			console.log('last query:', diag.lastQuery, '| counts:', diag.lastResultCounts);
			console.log('last error:', diag.lastError);
			console.groupEnd();
		} catch (e) {}
		return diag;
	};

	function logWarn()  { try { console.warn.apply(console,  ['[ZymargAlgolia]'].concat([].slice.call(arguments))); } catch(e){} }
	function logError() { try { console.error.apply(console, ['[ZymargAlgolia]'].concat([].slice.call(arguments))); } catch(e){} }
	function logInfo()  { try { console.info.apply(console,  ['[ZymargAlgolia]'].concat([].slice.call(arguments))); } catch(e){} }

	/* ---------------------------------------------------------------- */
	/* Algolia REST client                                               */
	/* ---------------------------------------------------------------- */
	function paramsToQS(p) {
		if (!p) return '';
		return Object.keys(p).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(p[k]);
		}).join('&');
	}

	function createAlgoliaClient(appId, apiKey) {
		var hosts = [
			appId + '-dsn.algolia.net',
			appId + '-1.algolianet.com',
			appId + '-2.algolianet.com',
			appId + '-3.algolianet.com'
		];
		return {
			search: function (requests) {
				var body = JSON.stringify({
					requests: requests.map(function (r) {
						return { indexName: r.indexName, params: paramsToQS(r.params || {}) };
					})
				});
				var i = 0;
				function attempt() {
					if (i >= hosts.length) return Promise.reject(new Error('All Algolia hosts unreachable'));
					var host = hosts[i++];
					return fetch('https://' + host + '/1/indexes/*/queries', {
						method: 'POST',
						headers: {
							'X-Algolia-Application-Id': appId,
							'X-Algolia-API-Key': apiKey,
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: body, credentials: 'omit', mode: 'cors'
					}).then(function (res) {
						if (res.ok) return res.json();
						if (res.status >= 500 && i < hosts.length) return attempt();
						return res.json().catch(function () { return {}; }).then(function (j) {
							var err = new Error((j && j.message) ? j.message : ('HTTP ' + res.status));
							err.status = res.status; throw err;
						});
					}).catch(function (err) {
						if (!err.status && i < hosts.length) return attempt();
						throw err;
					});
				}
				return attempt();
			}
		};
	}

	/* ---------------------------------------------------------------- */
	/* Boot                                                              */
	/* ---------------------------------------------------------------- */
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	function tryScanWithRetry(maxAttempts, intervalMs) {
		var attempt = 0;
		(function loop() {
			scan();
			attempt++;
			if (diag.wrappersBooted > 0 || attempt >= maxAttempts) return;
			setTimeout(loop, intervalMs);
		})();
	}

	ready(function () {
		if (typeof window.fetch !== 'function') {
			logWarn('window.fetch unavailable; instant search disabled.');
			return;
		}
		tryScanWithRetry(8, 250);
		if (window.MutationObserver) {
			var t;
			new MutationObserver(function () {
				clearTimeout(t); t = setTimeout(scan, 120);
			}).observe(document.documentElement, { childList: true, subtree: true });
		}
		logInfo('v' + VERSION + ' ready.');
	});

	function scan() {
		var cfg     = window.ZymargAlgolia;
		var wrappers = document.querySelectorAll('[data-zymarg-search], .zymarg-algolia-wrapper');
		diag.wrappersFound = wrappers.length;

		if (!cfg) {
			if (!scan.__warnedNoCfg) { logWarn('ZymargAlgolia config missing.'); scan.__warnedNoCfg = true; }
			return;
		}
		diag.configFound = true;
		diag.configValid = !!(cfg.appId && cfg.searchKey);
		if (!cfg.appId || !cfg.searchKey) {
			if (!scan.__warnedBadCfg) { logWarn('ZymargAlgolia: missing appId or searchKey.'); scan.__warnedBadCfg = true; }
			return;
		}
		if (!wrappers.length) return;

		var booted = 0;
		Array.prototype.forEach.call(wrappers, function (wrapper) {
			if (wrapper.__zymargBooted) return;
			wrapper.__zymargBooted = true;
			initWrapper(wrapper, cfg);
			booted++;
		});
		diag.wrappersBooted += booted;
		if (booted > 0) logInfo('booted ' + booted + ' wrapper(s).');
	}

	/* ---------------------------------------------------------------- */
	/* Helpers                                                           */
	/* ---------------------------------------------------------------- */
	function escapeHtml(str) {
		if (str == null) return '';
		return String(str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	function getHighlight(hit, attr) {
		if (hit && hit._highlightResult && hit._highlightResult[attr] &&
			typeof hit._highlightResult[attr].value === 'string') {
			return hit._highlightResult[attr].value;
		}
		return escapeHtml(hit && hit[attr] ? hit[attr] : '');
	}

	/* ---------------------------------------------------------------- */
	/* Wrapper init                                                      */
	/* ---------------------------------------------------------------- */
	function initWrapper(wrapper, cfg) {
		var input      = wrapper.querySelector('.zymarg-algolia-input');
		var dropdown   = wrapper.querySelector('.zymarg-algolia-dropdown');
		var resultsBox = wrapper.querySelector('.zymarg-algolia-results');
		var emptyBox   = wrapper.querySelector('.zymarg-algolia-empty');
		var loadingBox = wrapper.querySelector('.zymarg-algolia-loading');
		var clearBtn   = wrapper.querySelector('.zymarg-algolia-clear');
		var form       = wrapper.querySelector('.zymarg-algolia-form');

		if (!input || !dropdown || !resultsBox || !emptyBox) {
			logWarn('search wrapper missing required elements; skipping.');
			return;
		}

		// Section flags.
		var showProducts    = wrapper.getAttribute('data-show-products')   !== '0';
		var showCategories  = wrapper.getAttribute('data-show-categories') !== '0';
		var showVendors     = wrapper.getAttribute('data-show-vendors')    === '1';
		var categoryScope   = wrapper.getAttribute('data-category-scope')  === '1'; // Feature 6

		var spinnerMode = wrapper.getAttribute('data-spinner-mode') || 'searching';
		if (spinnerMode !== 'searching' && spinnerMode !== 'focus' && spinnerMode !== 'hidden') {
			spinnerMode = 'searching';
		}

		// Smart-feature flags (2.0.0). A missing/undefined flag means ENABLED,
		// so older cached config or any glitch can never silently break a feature.
		var FEAT = (cfg && cfg.features) || {};
		var featOn = function (v) { return (v === undefined || v === null) ? true : (v !== 0 && v !== '0' && v !== false); };
		var featRecent      = featOn(FEAT.recent);
		var featKeyboard    = featOn(FEAT.keyboard);
		var featInsights    = featOn(FEAT.insights);
		var featRelated     = featOn(FEAT.related);
		var featResultCount = featOn(FEAT.resultCount);

		// Empty state text.
		var emptyText = emptyBox.querySelector('.zymarg-algolia-empty-text');
		var emptyBtn  = emptyBox.querySelector('.zymarg-algolia-empty-btn');
		if (emptyText) emptyText.textContent = cfg.noResultsText || "Couldn't find what you're looking for?";
		if (emptyBtn) {
			emptyBtn.textContent = cfg.requestBtn || 'Request Here';
			emptyBtn.setAttribute('href', cfg.communityUrl || '/community');
		}

		var client    = createAlgoliaClient(cfg.appId, cfg.searchKey);
		var lastReqId = 0;

		/* -------------------------------------------------------------- */
		/* Feature 6 — Category scope state                                */
		/* -------------------------------------------------------------- */
		var activeScopeCategory = null; // { objectID, name, filterValue }

		/* -------------------------------------------------------------- */
		/* Feature 1 — Progress bar helpers                                */
		/* .is-searching on wrapper shows the CSS progress bar.            */
		/* -------------------------------------------------------------- */
		var startProgress = function () { wrapper.classList.add('is-searching'); };
		var stopProgress  = function () { wrapper.classList.remove('is-searching'); };

		/* -------------------------------------------------------------- */
		/* Dropdown visibility                                             */
		/* -------------------------------------------------------------- */
		var openDropdown  = function () { dropdown.hidden = false; };
		var closeDropdown = function () {
			dropdown.hidden = true;
			emptyBox.hidden = true;
			loadingBox.hidden = true;
			stopProgress();
		};
		var showLoading = function () {
			if (spinnerMode === 'hidden') return;
			loadingBox.hidden = false;
		};
		var hideLoading = function () { loadingBox.hidden = true; };

		var renderEmpty = function () {
			resultsBox.innerHTML = '';
			if (wrapper.classList.contains('zymarg-no-empty')) { closeDropdown(); return; }
			emptyBox.hidden = false;
			stopProgress();
			openDropdown();
		};

		/* -------------------------------------------------------------- */
		/* renderResults — Products → Categories → Vendors                 */
		/* Prepends category scope strip if Feature 6 is ON.              */
		/* -------------------------------------------------------------- */
		var renderResults = function (productHits, catHits, vendorHits, query, queryIDs, counts, relatedFor) {
			emptyBox.hidden = true;
			stopProgress();
			queryIDs = queryIDs || { products: '', categories: '', vendors: '' };
			counts   = counts   || { products: productHits.length, categories: catHits.length, vendors: vendorHits.length };

			var html = '';

			// ── Feature 6: active scope badge ────────────────────────────
			if (categoryScope && activeScopeCategory) {
				html += '<div class="zymarg-scope-active">' +
					'<span class="zymarg-scope-active-label">Searching in</span>' +
					'<span class="zymarg-scope-active-name">' + escapeHtml(activeScopeCategory.name) + '</span>' +
					'<button type="button" class="zymarg-scope-clear" aria-label="Clear category filter">&times;</button>' +
				'</div>';
			}

			// Related header.
			if (relatedFor) {
				html += '<div class="zymarg-algolia-related-header">' +
					escapeHtml(cfg.i18n.relatedFor || 'Showing related results for') +
					' <strong>' + escapeHtml(relatedFor) + '</strong></div>';
			}

			// Result count.
			var totalCount = (counts.products || 0) + (counts.categories || 0) + (counts.vendors || 0);
			if (featResultCount && totalCount > 0) {
				html += '<div class="zymarg-algolia-count">' +
					'<span class="zymarg-algolia-count-num">' + totalCount + '</span> ' +
					escapeHtml(totalCount === 1
						? (cfg.i18n.resultSingular || 'result')
						: (cfg.i18n.resultPlural   || 'results')) +
				'</div>';
			}

			// ── 1. Products ───────────────────────────────────────────────
			if (productHits && productHits.length) {
				var qidP = queryIDs.products || '';
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.products) +
					(counts.products ? ' <span class="zymarg-algolia-section-count">(' + counts.products + ')</span>' : '') +
					'</h4>';
				productHits.forEach(function (h, idx) {
					var price = h.price_html
						? h.price_html
						: (h.price ? (cfg.currencySym + Number(h.price).toFixed(2)) : '');
					var vendor = h.vendor_name
						? '<span class="zymarg-algolia-hit-meta">' + escapeHtml(cfg.i18n.by) + ' ' + getHighlight(h, 'vendor_name') + '</span>'
						: '';
					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '"' +
						' data-zymarg-index="'    + escapeHtml(cfg.indexProducts) + '"' +
						' data-zymarg-queryid="'  + escapeHtml(qidP) + '"' +
						' data-zymarg-objectid="' + escapeHtml(h.objectID || '') + '"' +
						' data-zymarg-position="' + (idx + 1) + '">' +
						(h.thumbnail
							? '<img class="zymarg-algolia-hit-img" src="' + escapeHtml(h.thumbnail) + '" alt="" loading="lazy" />'
							: '<span class="zymarg-algolia-hit-img"></span>') +
						'<span class="zymarg-algolia-hit-body">' +
							'<span class="zymarg-algolia-hit-title">' + getHighlight(h, 'name') + '</span>' +
							vendor +
						'</span>' +
						(price ? '<span class="zymarg-algolia-hit-price">' + price + '</span>' : '') +
					'</a>';
				});
				html += '</div>';
			}

			// ── 2. Categories ─────────────────────────────────────────────
			// Feature 6: if scope is ON, categories become tappable scope pills.
			if (catHits && catHits.length) {
				var qidC = queryIDs.categories || '';
				if (categoryScope) {
					// Render as scope-selection pills, not navigation links.
					html += '<div class="zymarg-algolia-section zymarg-scope-section">' +
						'<h4 class="zymarg-algolia-section-title">' +
						escapeHtml(cfg.i18n.categories) +
						' <span class="zymarg-algolia-section-count zymarg-scope-hint">tap to filter</span>' +
						'</h4>' +
						'<div class="zymarg-scope-pills">';
					catHits.slice(0, 5).forEach(function (h) {
						var isActive = activeScopeCategory && activeScopeCategory.objectID === h.objectID;
						// data-scope-value uses h.slug which maps to category_slugs in product index.
						html += '<button type="button"' +
							' class="zymarg-scope-pill' + (isActive ? ' is-active' : '') + '"' +
							' data-scope-id="'    + escapeHtml(h.objectID || '') + '"' +
							' data-scope-name="'  + escapeHtml(h.name || '') + '"' +
							' data-scope-value="' + escapeHtml(h.slug || h.name.toLowerCase().replace(/\s+/g, '-') || '') + '">' +
							getHighlight(h, 'name') +
							(h.count ? ' <span class="zymarg-scope-pill-count">(' + h.count + ')</span>' : '') +
						'</button>';
					});
					html += '</div></div>';
				} else {
					// Normal navigation links.
					html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
						escapeHtml(cfg.i18n.categories) +
						(counts.categories ? ' <span class="zymarg-algolia-section-count">(' + counts.categories + ')</span>' : '') +
						'</h4>';
					catHits.slice(0, 3).forEach(function (h, idx) {
						html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '"' +
							' data-zymarg-index="'    + escapeHtml(cfg.indexCats) + '"' +
							' data-zymarg-queryid="'  + escapeHtml(qidC) + '"' +
							' data-zymarg-objectid="' + escapeHtml(h.objectID || '') + '"' +
							' data-zymarg-position="' + (idx + 1) + '">' +
							'<span class="zymarg-algolia-cat-img">' +
								(h.image ? '<img src="' + escapeHtml(h.image) + '" alt="" loading="lazy" />' : '') +
							'</span>' +
							'<span class="zymarg-algolia-hit-body">' +
								'<span class="zymarg-algolia-hit-title">' + getHighlight(h, 'name') + '</span>' +
								'<span class="zymarg-algolia-hit-meta">' + (h.count || 0) + ' products</span>' +
							'</span>' +
						'</a>';
					});
					html += '</div>';
				}
			}

			// ── 3. Vendors ────────────────────────────────────────────────
			if (vendorHits && vendorHits.length) {
				var qidV = queryIDs.vendors || '';
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.vendors) +
					(counts.vendors ? ' <span class="zymarg-algolia-section-count">(' + counts.vendors + ')</span>' : '') +
					'</h4>';
				vendorHits.slice(0, 4).forEach(function (h, idx) {
					var initials = (h.name || '').trim().charAt(0).toUpperCase() || 'Z';
					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '"' +
						' data-zymarg-index="'    + escapeHtml(cfg.indexVendors) + '"' +
						' data-zymarg-queryid="'  + escapeHtml(qidV) + '"' +
						' data-zymarg-objectid="' + escapeHtml(h.objectID || '') + '"' +
						' data-zymarg-position="' + (idx + 1) + '">' +
						'<span class="zymarg-algolia-vendor-avatar">' +
							(h.avatar ? '<img src="' + escapeHtml(h.avatar) + '" alt="" loading="lazy" />' : escapeHtml(initials)) +
						'</span>' +
						'<span class="zymarg-algolia-hit-body">' +
							'<span class="zymarg-algolia-hit-title">' + getHighlight(h, 'name') + '</span>' +
							'<span class="zymarg-algolia-hit-meta">' + (h.product_count || 0) + ' products</span>' +
						'</span>' +
					'</a>';
				});
				html += '</div>';
			}

			// "See all" link.
			if (query) {
				var url = (form && form.getAttribute('action')) || (window.location.origin + '/');
				url += (url.indexOf('?') >= 0 ? '&' : '?') + 's=' + encodeURIComponent(query) + '&post_type=product';
				html += '<a class="zymarg-algolia-viewall" href="' + escapeHtml(url) + '">' +
					escapeHtml(cfg.i18n.viewAll) + ' &rarr;</a>';
			}

			resultsBox.innerHTML = html;
			resetActiveHit();
			openDropdown();
		};

		/* -------------------------------------------------------------- */
		/* calculate product hits per viewport                             */
		/* -------------------------------------------------------------- */
		var calculateProductHits = function () {
			var screenH    = window.innerHeight || 600;
			var reserved   = 220;
			var itemHeight = 64;
			return Math.min(50, Math.max(4, Math.floor((screenH - reserved) / itemHeight)));
		};

		/* -------------------------------------------------------------- */
		/* Feature 6 — build Algolia category filter string                */
		/* Uses category_slugs facet (confirmed in Algolia index config).  */
		/* -------------------------------------------------------------- */
		var buildCategoryFilter = function () {
			if (!categoryScope || !activeScopeCategory) return null;
			return 'category_slugs:"' + activeScopeCategory.filterValue.replace(/"/g, '\\"') + '"';
		};

		/* -------------------------------------------------------------- */
		/* search() — immediate, race-protected via lastReqId              */
		/* Feature 1: startProgress() at start, stopProgress() on finish.  */
		/* Feature 6: injects category filter when scope is active.        */
		/* -------------------------------------------------------------- */
		var search = function (query) {
			diag.lastQuery = query;
			if (!query) { renderEmptyStateContent(); return; }

			startProgress(); // Feature 1
			showLoading();
			openDropdown();

			var requests    = [];
			var resultTypes = [];
			var catFilter   = buildCategoryFilter(); // Feature 6

			if (showProducts) {
				var pParams = { query: query, hitsPerPage: calculateProductHits(), clickAnalytics: true };
				if (catFilter) pParams.filters = catFilter;
				requests.push({ indexName: cfg.indexProducts, params: pParams });
				resultTypes.push('products');
			}
			if (showCategories) {
				requests.push({ indexName: cfg.indexCats, params: { query: query, hitsPerPage: 5, clickAnalytics: true } });
				resultTypes.push('categories');
			}
			if (showVendors) {
				requests.push({ indexName: cfg.indexVendors, params: { query: query, hitsPerPage: 4, clickAnalytics: true } });
				resultTypes.push('vendors');
			}
			if (!requests.length) { hideLoading(); stopProgress(); closeDropdown(); return; }

			var reqId = ++lastReqId;

			client.search(requests).then(function (res) {
				if (reqId !== lastReqId) return;
				hideLoading();
				stopProgress(); // Feature 1

				var byType   = { products: [], categories: [], vendors: [] };
				var counts   = { products: 0,  categories: 0,  vendors: 0  };
				var queryIDs = { products: '', categories: '', vendors: ''  };

				((res && res.results) || []).forEach(function (r, i) {
					var type       = resultTypes[i];
					byType[type]   = r.hits || [];
					counts[type]   = (typeof r.nbHits === 'number') ? r.nbHits : (r.hits || []).length;
					queryIDs[type] = r.queryID || '';
				});
				diag.lastResultCounts = counts;

				var totalHits = byType.products.length + byType.categories.length + byType.vendors.length;
				if (totalHits === 0) {
					if (featRelated) { tryRelatedFallback(query, reqId); } else { renderEmpty(); }
					return;
				}

				renderResults(byType.products, byType.categories, byType.vendors, query, queryIDs, counts);
			}).catch(function (err) {
				if (reqId !== lastReqId) return;
				hideLoading(); stopProgress();
				diag.lastError = (err && err.message) ? err.message : String(err);
				logError('search failed:', err);
				renderEmpty();
			});
		};

		/* -------------------------------------------------------------- */
		/* Related results fallback                                        */
		/* -------------------------------------------------------------- */
		var tryRelatedFallback = function (query, reqId) {
			if (!showProducts || !cfg.indexProducts) { renderEmpty(); return; }
			showLoading(); startProgress(); openDropdown();
			client.search([{
				indexName: cfg.indexProducts,
				params: { query: query, hitsPerPage: calculateProductHits(), clickAnalytics: true, removeWordsIfNoResults: 'allOptional' }
			}]).then(function (res) {
				if (reqId !== lastReqId) return;
				hideLoading(); stopProgress();
				var r = (res && res.results && res.results[0]) || {};
				var hits = r.hits || [];
				if (!hits.length) { renderEmpty(); return; }
				renderResults(hits, [], [], query,
					{ products: r.queryID || '', categories: '', vendors: '' },
					{ products: (typeof r.nbHits === 'number') ? r.nbHits : hits.length, categories: 0, vendors: 0 },
					query
				);
			}).catch(function () {
				if (reqId !== lastReqId) return;
				hideLoading(); stopProgress(); renderEmpty();
			});
		};

		/* -------------------------------------------------------------- */
		/* Empty state: Recent + Trending (Feature 7 uses sync layer)      */
		/* -------------------------------------------------------------- */
		var renderEmptyStateContent = function () {
			var trending = (cfg.showTrending !== 0 && cfg.trendingSearches && Array.isArray(cfg.trendingSearches))
				? cfg.trendingSearches : [];

			syncGetRecent(cfg, function (recentRaw) {
				var recent = featRecent ? recentRaw : [];
				if (!recent.length && !trending.length) { closeDropdown(); return; }

				emptyBox.hidden = true;
				var html = '';

				if (recent.length) {
					html += '<div class="zymarg-algolia-section zymarg-algolia-recent">' +
						'<div class="zymarg-es-row">' +
							'<span class="zymarg-es-row-icon zymarg-es-row-icon--recent" aria-hidden="true"></span>' +
							'<span class="zymarg-es-row-label">' +
								escapeHtml(cfg.i18n.recentSearches || 'Recent searches') +
								' <a href="#" class="zymarg-algolia-recent-clear">' +
									escapeHtml(cfg.i18n.clear || 'Clear') +
								'</a>' +
							'</span>' +
						'</div>' +
						'<div class="zymarg-es-pills">';
					recent.forEach(function (q) {
						html += '<button type="button" class="zymarg-es-pill" data-q="' +
							escapeHtml(q) + '">' + escapeHtml(q) + '</button>';
					});
					html += '</div></div>';
				}

				if (trending.length) {
					html += '<div class="zymarg-algolia-section zymarg-algolia-trending">' +
						'<div class="zymarg-es-row">' +
							'<span class="zymarg-es-row-icon zymarg-es-row-icon--trending" aria-hidden="true"></span>' +
							'<span class="zymarg-es-row-label">' +
								escapeHtml(cfg.i18n.trendingSearches || 'Trending searches') +
							'</span>' +
						'</div>' +
						'<div class="zymarg-es-pills">';
					trending.forEach(function (q) {
						html += '<button type="button" class="zymarg-es-pill" data-q="' +
							escapeHtml(q) + '">' + escapeHtml(q) + '</button>';
					});
					html += '</div></div>';
				}

				resultsBox.innerHTML = html;
				resetActiveHit();
				openDropdown();
			});
		};

		/* -------------------------------------------------------------- */
		/* Keyboard navigation                                             */
		/* -------------------------------------------------------------- */
		var activeHit = null;
		var resetActiveHit = function () {
			if (activeHit) { activeHit.classList.remove('is-active'); activeHit = null; }
		};
		var moveActiveHit = function (dir) {
			var hits = wrapper.querySelectorAll('.zymarg-algolia-hit');
			if (!hits.length) return;
			var current = -1;
			Array.prototype.forEach.call(hits, function (h, i) { if (h === activeHit) current = i; });
			var next = dir > 0
				? (current + 1) % hits.length
				: (current <= 0 ? hits.length - 1 : current - 1);
			if (activeHit) activeHit.classList.remove('is-active');
			activeHit = hits[next];
			activeHit.classList.add('is-active');
			if (typeof activeHit.scrollIntoView === 'function') activeHit.scrollIntoView({ block: 'nearest' });
		};

		/* -------------------------------------------------------------- */
		/* Dropdown event delegation                                        */
		/* -------------------------------------------------------------- */
		dropdown.addEventListener('click', function (e) {
			// Empty-state pill.
			var pill = e.target.closest && e.target.closest('.zymarg-es-pill');
			if (pill) {
				e.preventDefault();
				var q = pill.getAttribute('data-q') || '';
				input.value = q;
				if (clearBtn) clearBtn.hidden = !q;
				search(q);
				return;
			}

			// Clear recent.
			var clearLink = e.target.closest && e.target.closest('.zymarg-algolia-recent-clear');
			if (clearLink) {
				e.preventDefault();
				syncClearRecent(cfg);
				renderEmptyStateContent();
				return;
			}

			// Feature 6 — scope pill tap.
			var scopePill = e.target.closest && e.target.closest('.zymarg-scope-pill');
			if (scopePill) {
				e.preventDefault();
				var sid   = scopePill.getAttribute('data-scope-id');
				var sname = scopePill.getAttribute('data-scope-name');
				var sval  = scopePill.getAttribute('data-scope-value');
				if (activeScopeCategory && activeScopeCategory.objectID === sid) {
					// Tap same pill again → clear scope.
					activeScopeCategory = null;
				} else {
					activeScopeCategory = { objectID: sid, name: sname, filterValue: sval };
				}
				search(input.value.trim());
				return;
			}

			// Feature 6 — clear scope badge button.
			var scopeClear = e.target.closest && e.target.closest('.zymarg-scope-clear');
			if (scopeClear) {
				e.preventDefault();
				activeScopeCategory = null;
				search(input.value.trim());
				return;
			}

			// Hit click → Insights.
			var hit = e.target.closest && e.target.closest('.zymarg-algolia-hit');
			if (hit) {
				var indexName = hit.getAttribute('data-zymarg-index');
				var queryID   = hit.getAttribute('data-zymarg-queryid');
				var objectID  = hit.getAttribute('data-zymarg-objectid');
				var position  = parseInt(hit.getAttribute('data-zymarg-position'), 10) || 0;
				if (featInsights && indexName && queryID && objectID) sendInsightsEvent(cfg, indexName, queryID, objectID, position);
				if (featRecent) syncPushRecent(cfg, input.value.trim()); // Feature 7
			}
		});

		/* -------------------------------------------------------------- */
		/* Input events — immediate, no debounce                           */
		/* -------------------------------------------------------------- */
		var handleInput = function () {
			var q = (input.value || '').trim();
			if (clearBtn) clearBtn.hidden = !q;
			// Feature 6 — clear scope when user clears the input.
			if (!q) activeScopeCategory = null;
			search(q);
		};

		input.addEventListener('input',          handleInput);
		input.addEventListener('keyup',          handleInput);
		input.addEventListener('paste',          function () { setTimeout(handleInput, 0); });
		input.addEventListener('compositionend', handleInput);
		input.addEventListener('change',         handleInput);

		input.addEventListener('focus', function () {
			var q = (input.value || '').trim();
			if (spinnerMode === 'focus') { showLoading(); openDropdown(); }
			if (q) search(q);
			else renderEmptyStateContent();
		});

		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape'    || e.keyCode === 27) { closeDropdown(); input.blur(); return; }
			if (!featKeyboard) return;
			if (e.key === 'ArrowDown' || e.keyCode === 40) { e.preventDefault(); moveActiveHit(+1); return; }
			if (e.key === 'ArrowUp'   || e.keyCode === 38) { e.preventDefault(); moveActiveHit(-1); return; }
			if ((e.key === 'Enter'    || e.keyCode === 13) && activeHit) {
				e.preventDefault();
				var indexName = activeHit.getAttribute('data-zymarg-index');
				var queryID   = activeHit.getAttribute('data-zymarg-queryid');
				var objectID  = activeHit.getAttribute('data-zymarg-objectid');
				var position  = parseInt(activeHit.getAttribute('data-zymarg-position'), 10) || 0;
				if (featInsights && indexName && queryID && objectID) sendInsightsEvent(cfg, indexName, queryID, objectID, position);
				if (featRecent) syncPushRecent(cfg, input.value.trim()); // Feature 7
				var href = activeHit.getAttribute('href');
				if (href) window.location.href = href;
			}
		});

		// Form submit.
		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var query = input.value.trim();
				if (!query) return;
				if (featRecent) syncPushRecent(cfg, query); // Feature 7
				closeDropdown();
				window.location.href = '/search-results/?q=' + encodeURIComponent(query);
			});
		}

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				clearBtn.hidden = true;
				activeScopeCategory = null; // Feature 6
				closeDropdown();
				input.focus();
			});
		}

		// Click outside → close.
		document.addEventListener('click', function (e) {
			if (!wrapper.contains(e.target)) closeDropdown();
		});
	}
})();
