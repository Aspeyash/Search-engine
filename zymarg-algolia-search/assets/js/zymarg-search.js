/*!
 * ZYMARG Algolia Search - Frontend instant search.
 * v1.0.7
 *
 * Talks to the Algolia REST API directly via window.fetch(). No external
 * UMD library, no CDN dependency. Multi-host failover (-dsn -> -1 -> -2
 * -> -3). Race-protected requests, MutationObserver re-scan, and a clean
 * fallback to the standard WP search page on submit.
 *
 * v1.0.7 hardening:
 *   - Leading semicolon + IIFE so script combiners cannot break the boot.
 *   - Wrappers found via [data-zymarg-search] OR .zymarg-algolia-wrapper
 *     (defends against HTML minifiers that strip data-* attributes).
 *   - Multiple input event listeners (input, keyup, paste, compositionend)
 *     so IME / paste / autofill all trigger the dropdown.
 *   - Verbose console diagnostic logging — run zymargAlgoliaDebug() in
 *     DevTools to see exactly where things stand.
 *   - Retries config detection in case wp_localize_script was delayed by
 *     a JS deferring/combining plugin.
 */
;(function () {
	'use strict';

	var VERSION = '1.0.17';

	/* ---------------------------------------------------------------- */
	/* Local-storage helpers (recent searches + anonymous user token).  */
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
		if (!t) {
			t = 'anonymous-' + uuidv4();
			safeLocalSet(STORAGE_USER, t);
		}
		return t;
	}

	function getRecentSearches() {
		try {
			var raw = safeLocalGet(STORAGE_RECENT);
			if (!raw) return [];
			var arr = JSON.parse(raw);
			return Array.isArray(arr) ? arr : [];
		} catch (e) {
			return [];
		}
	}

	function addRecentSearch(q) {
		q = (q || '').trim();
		if (!q) return;
		var list = getRecentSearches().filter(function (s) {
			return s && String(s).toLowerCase() !== q.toLowerCase();
		});
		list.unshift(q);
		if (list.length > RECENT_LIMIT) list = list.slice(0, RECENT_LIMIT);
		try { safeLocalSet(STORAGE_RECENT, JSON.stringify(list)); } catch (e) {}
	}

	function clearRecentSearches() {
		safeLocalRemove(STORAGE_RECENT);
	}

	/* ---------------------------------------------------------------- */
	/* Algolia Insights — click event tracking (fire-and-forget).        */
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
				var beaconUrl = 'https://insights.algolia.io/1/events' +
					'?X-Algolia-Application-Id=' + encodeURIComponent(cfg.appId) +
					'&X-Algolia-API-Key=' + encodeURIComponent(cfg.searchKey);
				navigator.sendBeacon(beaconUrl, new Blob([body], { type: 'application/json' }));
				return;
			}

			fetch('https://insights.algolia.io/1/events', {
				method: 'POST',
				headers: {
					'X-Algolia-Application-Id': cfg.appId,
					'X-Algolia-API-Key':        cfg.searchKey,
					'Content-Type':             'application/json'
				},
				body: body,
				keepalive: true,
				mode: 'cors',
				credentials: 'omit'
			}).catch(function () {});
		} catch (e) {}
	}

	/* ---------------------------------------------------------------- */
	/* Diagnostic state. zymargAlgoliaDebug() reads this.                */
	/* ---------------------------------------------------------------- */

	var diag = {
		version: VERSION,
		fetchAvailable: typeof window.fetch === 'function',
		mutationObserver: typeof window.MutationObserver === 'function',
		configFound: false,
		configValid: false,
		wrappersFound: 0,
		wrappersBooted: 0,
		lastQuery: null,
		lastResultCounts: null,
		lastError: null
	};

	window.zymargAlgoliaDebug = function () {
		// Update live counts each call so the user can see the truth right now.
		var ws = document.querySelectorAll('[data-zymarg-search], .zymarg-algolia-wrapper');
		diag.wrappersFound = ws.length;
		diag.wrappersBooted = 0;
		Array.prototype.forEach.call(ws, function (w) {
			if (w.__zymargBooted) diag.wrappersBooted++;
		});
		diag.configFound = !!window.ZymargAlgolia;
		diag.configValid = !!(window.ZymargAlgolia && window.ZymargAlgolia.appId && window.ZymargAlgolia.searchKey);
		// Print a friendly summary AND return the object for inspection.
		try {
			console.group('[ZymargAlgolia] diagnostics');
			console.log('version:           ', diag.version);
			console.log('fetch available:   ', diag.fetchAvailable);
			console.log('MutationObserver:  ', diag.mutationObserver);
			console.log('config object:     ', window.ZymargAlgolia || '(missing!)');
			console.log('config valid:      ', diag.configValid);
			console.log('wrappers found:    ', diag.wrappersFound);
			console.log('wrappers booted:   ', diag.wrappersBooted);
			console.log('last query:        ', diag.lastQuery);
			console.log('last result counts:', diag.lastResultCounts);
			console.log('last error:        ', diag.lastError);
			console.groupEnd();
		} catch (e) { /* noop */ }
		return diag;
	};

	function logInfo() {
		if (window.console && window.console.info) {
			try { console.info.apply(console, ['[ZymargAlgolia]'].concat([].slice.call(arguments))); } catch (e) {}
		}
	}
	function logWarn() {
		if (window.console && window.console.warn) {
			try { console.warn.apply(console, ['[ZymargAlgolia]'].concat([].slice.call(arguments))); } catch (e) {}
		}
	}
	function logError() {
		if (window.console && window.console.error) {
			try { console.error.apply(console, ['[ZymargAlgolia]'].concat([].slice.call(arguments))); } catch (e) {}
		}
	}

	/* ---------------------------------------------------------------- */
	/* Built-in Algolia REST client (no external dependency).            */
	/* ---------------------------------------------------------------- */

	function paramsToQueryString(p) {
		if (!p) return '';
		var parts = [];
		for (var k in p) {
			if (Object.prototype.hasOwnProperty.call(p, k)) {
				parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(p[k]));
			}
		}
		return parts.join('&');
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
					requests: (requests || []).map(function (r) {
						return {
							indexName: r.indexName,
							params: paramsToQueryString(r.params || {})
						};
					})
				});

				var i = 0;
				function attempt() {
					if (i >= hosts.length) {
						return Promise.reject(new Error('All Algolia hosts unreachable'));
					}
					var host = hosts[i++];
					return fetch('https://' + host + '/1/indexes/*/queries', {
						method: 'POST',
						headers: {
							'X-Algolia-Application-Id': appId,
							'X-Algolia-API-Key': apiKey,
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: body,
						credentials: 'omit',
						mode: 'cors'
					}).then(function (res) {
						if (res.ok) return res.json();
						if (res.status >= 500 && i < hosts.length) return attempt();
						return res.json().catch(function () { return {}; }).then(function (j) {
							var msg = (j && j.message) ? j.message : ('HTTP ' + res.status);
							var err = new Error(msg);
							err.status = res.status;
							throw err;
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
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function tryScanWithRetry(maxAttempts, intervalMs) {
		var attempt = 0;
		(function loop() {
			scan();
			attempt++;
			diag.configFound = !!window.ZymargAlgolia;
			diag.configValid = !!(window.ZymargAlgolia && window.ZymargAlgolia.appId && window.ZymargAlgolia.searchKey);
			// Stop retrying once we've successfully booted at least one wrapper,
			// or we've hit the max attempts.
			if (diag.wrappersBooted > 0 || attempt >= maxAttempts) return;
			setTimeout(loop, intervalMs);
		})();
	}

	ready(function () {
		if (typeof window.fetch !== 'function') {
			logWarn('window.fetch is not available; instant search disabled. Submit will still go to the WP search page.');
			return;
		}

		// First scan + a few retries spaced out, in case wp_localize_script
		// was deferred by a script combiner / minifier and ZymargAlgolia
		// isn't on window yet at DOMContentLoaded.
		tryScanWithRetry(8, 250);

		// Re-scan when wrappers are dynamically added (block editor preview,
		// Elementor preview iframe, AJAX-loaded headers, etc).
		if (window.MutationObserver) {
			var t;
			var rescan = function () {
				clearTimeout(t);
				t = setTimeout(scan, 120);
			};
			new MutationObserver(rescan).observe(
				document.documentElement,
				{ childList: true, subtree: true }
			);
		}

		// One-line console banner so the user can confirm v1.0.7 is loaded.
		// Kept short on purpose so it's easy to spot.
		logInfo('v' + VERSION + ' ready. Run zymargAlgoliaDebug() for diagnostics.');
	});

	function scan() {
		var cfg = window.ZymargAlgolia;
		var wrappers = document.querySelectorAll('[data-zymarg-search], .zymarg-algolia-wrapper');
		diag.wrappersFound = wrappers.length;

		if (!cfg) {
			if (!scan.__warnedNoCfg) {
				logWarn('window.ZymargAlgolia is missing — wp_localize_script did not run, or a JS optimizer stripped it. Found ' + wrappers.length + ' search wrapper(s) but cannot init.');
				scan.__warnedNoCfg = true;
			}
			return;
		}
		diag.configFound = true;
		diag.configValid = !!(cfg.appId && cfg.searchKey);

		if (!cfg.appId || !cfg.searchKey) {
			if (!scan.__warnedBadCfg) {
				logWarn('ZymargAlgolia config is missing appId or searchKey. Set them in Settings -> ZYMARG Algolia.');
				scan.__warnedBadCfg = true;
			}
			return;
		}

		if (!wrappers.length) return;

		var newlyBooted = 0;
		Array.prototype.forEach.call(wrappers, function (wrapper) {
			if (wrapper.__zymargBooted) return;
			wrapper.__zymargBooted = true;
			initWrapper(wrapper, cfg);
			newlyBooted++;
		});
		diag.wrappersBooted += newlyBooted;
		if (newlyBooted > 0) {
			logInfo('booted ' + newlyBooted + ' search wrapper(s).');
		}
	}

	/* ---------------------------------------------------------------- */
	/* Helpers                                                           */
	/* ---------------------------------------------------------------- */

	function escapeHtml(str) {
		if (str == null) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function getHighlight(hit, attr) {
		if (
			hit &&
			hit._highlightResult &&
			hit._highlightResult[attr] &&
			typeof hit._highlightResult[attr].value === 'string'
		) {
			return hit._highlightResult[attr].value;
		}
		return escapeHtml(hit && hit[attr] ? hit[attr] : '');
	}

	function debounce(fn, ms) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(ctx, args); }, ms);
		};
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
			logWarn('a search wrapper is missing required inner elements; skipping init for it.');
			return;
		}

		// Read per-wrapper section visibility flags from data attributes set
		// by the renderer. Defaults: products + categories ON, vendors OFF.
		var showProducts   = wrapper.getAttribute('data-show-products')   !== '0';
		var showCategories = wrapper.getAttribute('data-show-categories') !== '0';
		var showVendors    = wrapper.getAttribute('data-show-vendors')    === '1';

		// 1.0.17: loading-spinner visibility mode.
		//   'searching' (default) — show only during the API call (existing behavior)
		//   'focus'               — show the moment the user touches/focuses the bar
		//   'hidden'              — never show
		var spinnerMode = wrapper.getAttribute('data-spinner-mode') || 'searching';
		if (spinnerMode !== 'searching' && spinnerMode !== 'focus' && spinnerMode !== 'hidden') {
			spinnerMode = 'searching';
		}

		// Empty-state CTA from settings.
		var emptyText = emptyBox.querySelector('.zymarg-algolia-empty-text');
		var emptyBtn  = emptyBox.querySelector('.zymarg-algolia-empty-btn');
		if (emptyText) emptyText.textContent = cfg.noResultsText || "Couldn't find what you're looking for?";
		if (emptyBtn) {
			emptyBtn.textContent = cfg.requestBtn || 'Request Here';
			emptyBtn.setAttribute('href', cfg.communityUrl || '/community');
		}

		var client = createAlgoliaClient(cfg.appId, cfg.searchKey);
		var lastReqId = 0;

		var openDropdown  = function () { dropdown.hidden = false; };
		var closeDropdown = function () {
			dropdown.hidden   = true;
			emptyBox.hidden   = true;
			loadingBox.hidden = true;
		};
		var showLoading = function () {
			if (spinnerMode === 'hidden') return;
			loadingBox.hidden = false;
		};
		var hideLoading = function () { loadingBox.hidden = true; };

		var renderEmpty = function () {
			resultsBox.innerHTML = '';
			// "Show empty message" toggle off → close dropdown completely
			// instead of showing the "Couldn't find..." CTA. This does not
			// affect when/how instant search fires; it only changes what
			// happens visually when zero results return.
			if (wrapper.classList.contains('zymarg-no-empty')) {
				closeDropdown();
				return;
			}
			emptyBox.hidden = false;
			openDropdown();
		};

		var renderResults = function (productHits, catHits, vendorHits, query, queryIDs, counts, relatedFor) {
			emptyBox.hidden = true;
			queryIDs = queryIDs || { products: '', categories: '', vendors: '' };
			counts   = counts   || { products: productHits.length, categories: catHits.length, vendorsHits: vendorHits.length };

			var html = '';

			// "Showing related results for X" header — only when the main
			// search returned zero hits and we fell back to allOptional.
			if (relatedFor) {
				html += '<div class="zymarg-algolia-related-header">' +
					escapeHtml(cfg.i18n.relatedFor || 'Showing related results for') +
					' <strong>' + escapeHtml(relatedFor) + '</strong></div>';
			}

			// Result count badge — total across all visible sections.
			var totalCount = (counts.products || 0) + (counts.categories || 0) + (counts.vendors || 0);
			if (totalCount > 0) {
				html += '<div class="zymarg-algolia-count">' +
					'<span class="zymarg-algolia-count-num">' + totalCount + '</span> ' +
					escapeHtml(totalCount === 1 ? (cfg.i18n.resultSingular || 'result') : (cfg.i18n.resultPlural || 'results')) +
				'</div>';
			}

			// Products first.
			if (productHits && productHits.length) {
				var qidProducts = queryIDs.products || '';
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.products) +
					(counts.products ? ' <span class="zymarg-algolia-section-count">(' + counts.products + ')</span>' : '') +
					'</h4>';
				productHits.slice(0, 6).forEach(function (h, idx) {
					var price = h.price_html
						? h.price_html
						: (h.price ? (cfg.currencySym + Number(h.price).toFixed(2)) : '');
					var vendor = h.vendor_name
						? '<span class="zymarg-algolia-hit-meta">' + escapeHtml(cfg.i18n.by) + ' ' +
							getHighlight(h, 'vendor_name') + '</span>'
						: '';

					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '"' +
						' data-zymarg-index="' + escapeHtml(cfg.indexProducts) + '"' +
						' data-zymarg-queryid="' + escapeHtml(qidProducts) + '"' +
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

			// Categories second.
			if (catHits && catHits.length) {
				var qidCats = queryIDs.categories || '';
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.categories) +
					(counts.categories ? ' <span class="zymarg-algolia-section-count">(' + counts.categories + ')</span>' : '') +
					'</h4>';
				catHits.slice(0, 3).forEach(function (h, idx) {
					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '"' +
						' data-zymarg-index="' + escapeHtml(cfg.indexCats) + '"' +
						' data-zymarg-queryid="' + escapeHtml(qidCats) + '"' +
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

			// Vendors last (default OFF — only renders when explicitly enabled).
			if (vendorHits && vendorHits.length) {
				var qidVendors = queryIDs.vendors || '';
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.vendors) +
					(counts.vendors ? ' <span class="zymarg-algolia-section-count">(' + counts.vendors + ')</span>' : '') +
					'</h4>';
				vendorHits.slice(0, 4).forEach(function (h, idx) {
					var initials = (h.name || '').trim().charAt(0).toUpperCase() || 'Z';
					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '"' +
						' data-zymarg-index="' + escapeHtml(cfg.indexVendors) + '"' +
						' data-zymarg-queryid="' + escapeHtml(qidVendors) + '"' +
						' data-zymarg-objectid="' + escapeHtml(h.objectID || '') + '"' +
						' data-zymarg-position="' + (idx + 1) + '">' +
						'<span class="zymarg-algolia-vendor-avatar">' +
							(h.avatar
								? '<img src="' + escapeHtml(h.avatar) + '" alt="" loading="lazy" />'
								: escapeHtml(initials)) +
						'</span>' +
						'<span class="zymarg-algolia-hit-body">' +
							'<span class="zymarg-algolia-hit-title">' + getHighlight(h, 'name') + '</span>' +
							'<span class="zymarg-algolia-hit-meta">' +
								(h.product_count || 0) + ' products' +
							'</span>' +
						'</span>' +
					'</a>';
				});
				html += '</div>';
			}

			// "See all" link -> standard WP search page (?s=) so SEO crawl works.
			if (query) {
				var url = (form && form.getAttribute('action')) || (window.location.origin + '/');
				url += (url.indexOf('?') >= 0 ? '&' : '?') +
					's=' + encodeURIComponent(query) + '&post_type=product';
				html += '<a class="zymarg-algolia-viewall" href="' + escapeHtml(url) + '">' +
					escapeHtml(cfg.i18n.viewAll) + ' &rarr;</a>';
			}

			resultsBox.innerHTML = html;
			resetActiveHit();
			openDropdown();
		};

		var search = function (query) {
			diag.lastQuery = query;
			if (!query) {
				renderEmptyStateContent();
				return;
			}
			showLoading();
			openDropdown();

			// Build the requests array based on which sections this widget
			// shows. Hidden sections aren't queried at all — saves Algolia
			// API calls and avoids errors when an index doesn't exist.
			// 1.0.15: clickAnalytics=true generates a queryID per index so we
			// can attribute clicks back to the search via the Insights API.
			var requests = [];
			var resultTypes = [];
			if (showProducts) {
				requests.push({ indexName: cfg.indexProducts, params: { query: query, hitsPerPage: 6, clickAnalytics: true } });
				resultTypes.push('products');
			}
			if (showCategories) {
				requests.push({ indexName: cfg.indexCats, params: { query: query, hitsPerPage: 3, clickAnalytics: true } });
				resultTypes.push('categories');
			}
			if (showVendors) {
				requests.push({ indexName: cfg.indexVendors, params: { query: query, hitsPerPage: 4, clickAnalytics: true } });
				resultTypes.push('vendors');
			}

			if (!requests.length) {
				hideLoading();
				closeDropdown();
				return;
			}

			var reqId = ++lastReqId;

			client.search(requests).then(function (res) {
				if (reqId !== lastReqId) return;
				hideLoading();

				var byType    = { products: [], categories: [], vendors: [] };
				var counts    = { products: 0,  categories: 0,  vendors: 0  };
				var queryIDs  = { products: '', categories: '', vendors: ''  };

				(((res && res.results) || [])).forEach(function (r, i) {
					var type = resultTypes[i];
					byType[type]    = r.hits || [];
					counts[type]    = (typeof r.nbHits === 'number') ? r.nbHits : (r.hits || []).length;
					queryIDs[type]  = r.queryID || '';
				});

				diag.lastResultCounts = counts;

				var totalHits = byType.products.length + byType.categories.length + byType.vendors.length;

				if (totalHits === 0) {
					// 1.0.15 "Did you mean" via related-products fallback.
					// Retry once with removeWordsIfNoResults: 'allOptional'
					// against the products index. If that returns hits, show
					// them with a "Showing related results" header.
					tryRelatedFallback(query, reqId);
					return;
				}

				renderResults(byType.products, byType.categories, byType.vendors, query, queryIDs, counts);
			}).catch(function (err) {
				if (reqId !== lastReqId) return;
				hideLoading();
				diag.lastError = (err && err.message) ? err.message : String(err);
				logError('search request failed:', err);
				renderEmpty();
			});
		};

		// "Did you mean" via related-products fallback — fires only when the
		// main search returned zero hits. One extra Algolia request, products
		// index only, with removeWordsIfNoResults so any word subset matches.
		var tryRelatedFallback = function (query, reqId) {
			if (!showProducts || !cfg.indexProducts) {
				renderEmpty();
				return;
			}
			showLoading();
			openDropdown();
			client.search([{
				indexName: cfg.indexProducts,
				params: {
					query: query,
					hitsPerPage: 6,
					clickAnalytics: true,
					removeWordsIfNoResults: 'allOptional'
				}
			}]).then(function (res) {
				if (reqId !== lastReqId) return;
				hideLoading();
				var r        = (res && res.results && res.results[0]) || {};
				var hits     = r.hits || [];
				var queryIDs = { products: r.queryID || '', categories: '', vendors: '' };
				var counts   = { products: (typeof r.nbHits === 'number') ? r.nbHits : hits.length, categories: 0, vendors: 0 };

				if (!hits.length) {
					renderEmpty();
					return;
				}
				renderResults(hits, [], [], query, queryIDs, counts, /* relatedFor */ query);
			}).catch(function () {
				if (reqId !== lastReqId) return;
				hideLoading();
				renderEmpty();
			});
		};

		/* ------------------------------------------------------------ */
		/* 1.0.15 additions: empty-state pills, keyboard nav, click track */
		/* ------------------------------------------------------------ */

		// Empty-state content: Recent searches + Trending searches.
		// Triggered when input is focused with no value, or when the user
		// erases their text back to empty.
		var renderEmptyStateContent = function () {
			var recent   = getRecentSearches();
			var trending = (cfg.trendingSearches && Array.isArray(cfg.trendingSearches)) ? cfg.trendingSearches : [];

			if (!recent.length && !trending.length) {
				closeDropdown();
				return;
			}

			emptyBox.hidden = true;
			var html = '';

			if (recent.length) {
				html += '<div class="zymarg-algolia-section zymarg-algolia-recent">' +
					'<h4 class="zymarg-algolia-section-title">' +
						escapeHtml(cfg.i18n.recentSearches || 'Recent searches') +
						' <a href="#" class="zymarg-algolia-recent-clear">' +
							escapeHtml(cfg.i18n.clear || 'Clear') +
						'</a>' +
					'</h4>' +
					'<div class="zymarg-algolia-pills">';
				recent.forEach(function (q) {
					html += '<button type="button" class="zymarg-algolia-pill zymarg-algolia-pill-recent" data-q="' +
						escapeHtml(q) + '">' + escapeHtml(q) + '</button>';
				});
				html += '</div></div>';
			}

			if (trending.length) {
				html += '<div class="zymarg-algolia-section zymarg-algolia-trending">' +
					'<h4 class="zymarg-algolia-section-title">' +
						escapeHtml(cfg.i18n.trendingSearches || 'Trending searches') +
					'</h4>' +
					'<div class="zymarg-algolia-pills">';
				trending.forEach(function (q) {
					html += '<button type="button" class="zymarg-algolia-pill zymarg-algolia-pill-trending" data-q="' +
						escapeHtml(q) + '">' + escapeHtml(q) + '</button>';
				});
				html += '</div></div>';
			}

			resultsBox.innerHTML = html;
			resetActiveHit();
			openDropdown();
		};

		// Keyboard navigation state.
		var activeHit = null;

		var resetActiveHit = function () {
			if (activeHit) {
				activeHit.classList.remove('is-active');
				activeHit = null;
			}
		};

		var moveActiveHit = function (dir) {
			var hits = wrapper.querySelectorAll('.zymarg-algolia-hit');
			if (!hits.length) return;

			var current = -1;
			Array.prototype.forEach.call(hits, function (h, i) {
				if (h === activeHit) current = i;
			});

			var next;
			if (dir > 0) {
				next = (current + 1) % hits.length;
			} else {
				next = (current <= 0) ? (hits.length - 1) : (current - 1);
			}

			if (activeHit) activeHit.classList.remove('is-active');
			activeHit = hits[next];
			activeHit.classList.add('is-active');
			if (typeof activeHit.scrollIntoView === 'function') {
				activeHit.scrollIntoView({ block: 'nearest' });
			}
		};

		// Track click on a hit so we can fire an Insights event before the
		// browser navigates. Uses event delegation on the dropdown so it
		// works for any hit including ones that get re-rendered later.
		dropdown.addEventListener('click', function (e) {
			// Pill click → re-run search with the chosen query.
			var pill = e.target.closest && e.target.closest('.zymarg-algolia-pill');
			if (pill) {
				e.preventDefault();
				var q = pill.getAttribute('data-q') || '';
				input.value = q;
				if (clearBtn) clearBtn.hidden = !q;
				search(q);
				return;
			}

			// "Clear recent" link.
			var clearLink = e.target.closest && e.target.closest('.zymarg-algolia-recent-clear');
			if (clearLink) {
				e.preventDefault();
				clearRecentSearches();
				renderEmptyStateContent();
				return;
			}

			// Hit click → fire Insights event (fire-and-forget).
			var hit = e.target.closest && e.target.closest('.zymarg-algolia-hit');
			if (hit) {
				var indexName = hit.getAttribute('data-zymarg-index');
				var queryID   = hit.getAttribute('data-zymarg-queryid');
				var objectID  = hit.getAttribute('data-zymarg-objectid');
				var position  = parseInt(hit.getAttribute('data-zymarg-position'), 10) || 0;
				if (indexName && queryID && objectID) {
					sendInsightsEvent(cfg, indexName, queryID, objectID, position);
				}
				// Also save the current query as a recent search.
				addRecentSearch(input.value);
				// Don't preventDefault — let the browser navigate normally.
			}
		});

		// Type -> instant search (debounced 100ms). Multiple event listeners
		// so IME composition, paste, autofill, and physical keys all trigger.
		var debounced = debounce(function () {
			var q = (input.value || '').trim();
			if (clearBtn) clearBtn.hidden = !q;
			search(q);
		}, 100);

		input.addEventListener('input',          debounced);
		input.addEventListener('keyup',          debounced);
		input.addEventListener('paste',          function () { setTimeout(debounced, 0); });
		input.addEventListener('compositionend', debounced);
		input.addEventListener('change',         debounced);
		input.addEventListener('focus', function () {
			var q = (input.value || '').trim();
			// 'focus' mode: show the spinner the moment the bar is touched.
			// It will be hidden again by closeDropdown (click-outside / Esc /
			// submit) or by hideLoading inside the search response handler.
			if (spinnerMode === 'focus') {
				showLoading();
				openDropdown();
			}
			if (q) {
				search(q);
			} else {
				renderEmptyStateContent();
			}
		});

		// Keyboard navigation: ↑/↓ move active hit, Enter opens it,
		// Esc closes the dropdown.
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				closeDropdown();
				input.blur();
				return;
			}
			if (e.key === 'ArrowDown' || e.keyCode === 40) {
				e.preventDefault();
				moveActiveHit(+1);
				return;
			}
			if (e.key === 'ArrowUp' || e.keyCode === 38) {
				e.preventDefault();
				moveActiveHit(-1);
				return;
			}
			if (e.key === 'Enter' || e.keyCode === 13) {
				if (activeHit) {
					e.preventDefault();
					var indexName = activeHit.getAttribute('data-zymarg-index');
					var queryID   = activeHit.getAttribute('data-zymarg-queryid');
					var objectID  = activeHit.getAttribute('data-zymarg-objectid');
					var position  = parseInt(activeHit.getAttribute('data-zymarg-position'), 10) || 0;
					if (indexName && queryID && objectID) {
						sendInsightsEvent(cfg, indexName, queryID, objectID, position);
					}
					addRecentSearch(input.value);
					var href = activeHit.getAttribute('href');
					if (href) {
						window.location.href = href;
					}
				}
				// else: let the form submit normally to /?s=...
			}
		});

		// Submit -> let WP standard search take over (SEO crawlable).
		if (form) {
			form.addEventListener('submit', function () {
				addRecentSearch(input.value);
				closeDropdown();
				var hidden = form.querySelector('input[name="post_type"]');
				if (!hidden) {
					hidden = document.createElement('input');
					hidden.type  = 'hidden';
					hidden.name  = 'post_type';
					hidden.value = 'product';
					form.appendChild(hidden);
				}
			});
		}

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				clearBtn.hidden = true;
				closeDropdown();
				input.focus();
			});
		}

		// Click outside -> close.
		document.addEventListener('click', function (e) {
			if (!wrapper.contains(e.target)) closeDropdown();
		});
	}
})();
