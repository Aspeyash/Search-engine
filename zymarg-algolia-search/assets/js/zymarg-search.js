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

	var VERSION = '1.0.13';

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
		var showLoading = function () { loadingBox.hidden = false; };
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

		var renderResults = function (productHits, catHits, vendorHits, query) {
			emptyBox.hidden = true;
			var html = '';

			// Products first — what users are mostly looking for.
			if (productHits && productHits.length) {
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.products) + '</h4>';
				productHits.slice(0, 6).forEach(function (h) {
					var price = h.price_html
						? h.price_html
						: (h.price ? (cfg.currencySym + Number(h.price).toFixed(2)) : '');
					var vendor = h.vendor_name
						? '<span class="zymarg-algolia-hit-meta">' + escapeHtml(cfg.i18n.by) + ' ' +
							getHighlight(h, 'vendor_name') + '</span>'
						: '';

					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '">' +
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
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.categories) + '</h4>';
				catHits.slice(0, 3).forEach(function (h) {
					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '">' +
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
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.vendors) + '</h4>';
				vendorHits.slice(0, 4).forEach(function (h) {
					var initials = (h.name || '').trim().charAt(0).toUpperCase() || 'Z';
					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '">' +
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
			openDropdown();
		};

		var search = function (query) {
			diag.lastQuery = query;
			if (!query) {
				closeDropdown();
				return;
			}
			showLoading();
			openDropdown();

			// Build the requests array based on which sections this widget
			// shows. Hidden sections aren't queried at all — saves Algolia
			// API calls and avoids errors when an index doesn't exist (e.g.
			// no Dokan vendors yet means no zymarg_vendors index).
			var requests = [];
			var resultTypes = [];
			if (showProducts) {
				requests.push({ indexName: cfg.indexProducts, params: { query: query, hitsPerPage: 6 } });
				resultTypes.push('products');
			}
			if (showCategories) {
				requests.push({ indexName: cfg.indexCats, params: { query: query, hitsPerPage: 3 } });
				resultTypes.push('categories');
			}
			if (showVendors) {
				requests.push({ indexName: cfg.indexVendors, params: { query: query, hitsPerPage: 4 } });
				resultTypes.push('vendors');
			}

			// Edge case: every section toggled off — just close the dropdown.
			if (!requests.length) {
				hideLoading();
				closeDropdown();
				return;
			}

			var reqId = ++lastReqId;

			client.search(requests).then(function (res) {
				if (reqId !== lastReqId) return;
				hideLoading();
				var hits = { products: [], categories: [], vendors: [] };
				(((res && res.results) || [])).forEach(function (r, i) {
					hits[resultTypes[i]] = r.hits || [];
				});
				diag.lastResultCounts = {
					products:   hits.products.length,
					categories: hits.categories.length,
					vendors:    hits.vendors.length
				};

				if (!hits.products.length && !hits.categories.length && !hits.vendors.length) {
					renderEmpty();
					return;
				}
				renderResults(hits.products, hits.categories, hits.vendors, query);
			}).catch(function (err) {
				if (reqId !== lastReqId) return;
				hideLoading();
				diag.lastError = (err && err.message) ? err.message : String(err);
				logError('search request failed:', err);
				renderEmpty();
			});
		};

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
			if (q) search(q);
		});

		// Submit -> let WP standard search take over (SEO crawlable).
		if (form) {
			form.addEventListener('submit', function () {
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

		// Esc -> close.
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				closeDropdown();
				input.blur();
			}
		});
	}
})();
