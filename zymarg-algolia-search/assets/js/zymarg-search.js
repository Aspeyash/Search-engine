/*!
 * ZYMARG Algolia Search - Frontend instant search.
 * v1.0.6
 *
 * Talks to the Algolia REST API directly via window.fetch(). No external
 * UMD library is loaded — this guarantees the search bar boots even if
 * jsDelivr (or any CDN) is blocked, slow, or cached as a stale failure
 * by an ad-blocker, WAF, or restrictive CSP.
 *
 * Multi-host failover (-dsn -> -1 -> -2 -> -3) so one DC outage never
 * breaks the search. Race-protected requests, MutationObserver re-scan
 * for block-editor / Elementor previews, and a clean fallback to the
 * standard WP search page on submit.
 */
(function () {
	'use strict';

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
		// Algolia DSN + 3 fallback hosts — gives ~99.99% availability.
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
						if (res.ok) {
							return res.json();
						}
						// 5xx -> try next host. 4xx -> surface the error.
						if (res.status >= 500 && i < hosts.length) {
							return attempt();
						}
						return res.json().catch(function () { return {}; }).then(function (j) {
							var msg = (j && j.message) ? j.message : ('HTTP ' + res.status);
							var err = new Error(msg);
							err.status = res.status;
							throw err;
						});
					}).catch(function (err) {
						// Network error -> try next host.
						if (!err.status && i < hosts.length) {
							return attempt();
						}
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

	ready(function () {
		if (typeof window.fetch !== 'function') {
			if (window.console && window.console.warn) {
				console.warn('[ZymargAlgolia] window.fetch is not available; instant search disabled. Submit will still go to the WP search page.');
			}
			return;
		}

		scan();

		// Re-scan when new wrappers appear (block editor preview, Elementor
		// preview iframe, AJAX-loaded headers, etc).
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

		// One-line console banner so the user can confirm v1.0.6 is loaded.
		if (window.console && window.console.info) {
			console.info('[ZymargAlgolia] v1.0.6 ready');
		}
	});

	function scan() {
		var cfg = window.ZymargAlgolia;
		if (!cfg || !cfg.appId || !cfg.searchKey) return;

		var wrappers = document.querySelectorAll('[data-zymarg-search]');
		if (!wrappers.length) return;

		Array.prototype.forEach.call(wrappers, function (wrapper) {
			if (wrapper.__zymargBooted) return;
			wrapper.__zymargBooted = true;
			initWrapper(wrapper, cfg);
		});
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

		if (!input || !dropdown || !resultsBox || !emptyBox) return;

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
			emptyBox.hidden = false;
			openDropdown();
		};

		var renderResults = function (productHits, vendorHits, catHits, query) {
			emptyBox.hidden = true;
			var html = '';

			// Categories first — quickest to scan visually.
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

			// Products.
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

			// Vendors.
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
			if (!query) {
				closeDropdown();
				return;
			}
			showLoading();
			openDropdown();

			var requests = [
				{ indexName: cfg.indexProducts, params: { query: query, hitsPerPage: 6 } },
				{ indexName: cfg.indexVendors,  params: { query: query, hitsPerPage: 4 } },
				{ indexName: cfg.indexCats,     params: { query: query, hitsPerPage: 3 } }
			];

			var reqId = ++lastReqId;

			client.search(requests).then(function (res) {
				if (reqId !== lastReqId) return; // a newer search has fired

				hideLoading();
				var p = (res && res.results && res.results[0]) || {};
				var v = (res && res.results && res.results[1]) || {};
				var c = (res && res.results && res.results[2]) || {};
				var pHits = p.hits || [];
				var vHits = v.hits || [];
				var cHits = c.hits || [];

				if (!pHits.length && !vHits.length && !cHits.length) {
					renderEmpty();
					return;
				}
				renderResults(pHits, vHits, cHits, query);
			}).catch(function (err) {
				if (reqId !== lastReqId) return;
				hideLoading();
				if (window.console) console.error('[ZymargAlgolia]', err);
				// Show the friendly CTA so the user has somewhere to go.
				renderEmpty();
			});
		};

		// Type -> instant search (debounced 100ms).
		var debounced = debounce(function () {
			var q = (input.value || '').trim();
			if (clearBtn) clearBtn.hidden = !q;
			search(q);
		}, 100);

		input.addEventListener('input', debounced);
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
