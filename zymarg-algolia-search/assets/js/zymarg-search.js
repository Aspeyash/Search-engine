/*!
 * ZYMARG Algolia Search - Frontend instant search.
 * v1.0.5
 *
 * Renders a multi-index dropdown (products / vendors / categories) using
 * Algolia's lite client. No InstantSearch.js dependency. No page reload
 * while typing — the dropdown opens as soon as the user types one character.
 */
(function () {
	'use strict';

	var BOOT_TIMEOUT_MS = 8000;

	/* ---------------------------------------------------------------- */
	/* Boot                                                             */
	/* ---------------------------------------------------------------- */

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function waitForLib(maxMs, cb) {
		var start = Date.now();
		(function poll() {
			if (typeof window.algoliasearch === 'function') {
				cb(true);
				return;
			}
			if (Date.now() - start > maxMs) {
				cb(false);
				return;
			}
			setTimeout(poll, 80);
		})();
	}

	ready(function () {
		waitForLib(BOOT_TIMEOUT_MS, function (ok) {
			if (!ok) {
				if (window.console && window.console.warn) {
					console.warn('[ZymargAlgolia] algoliasearch library failed to load.');
				}
				return;
			}
			scan();

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
		});
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
	/* Helpers                                                          */
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
			// Algolia already wraps matches in <mark> via highlightPreTag/PostTag.
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
	/* Wrapper init                                                     */
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

		var client = window.algoliasearch(cfg.appId, cfg.searchKey);
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
				// Race-protect: only render the latest query's results.
				if (reqId !== lastReqId) return;

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
				// Still show the friendly "request" CTA so the user has somewhere to go.
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
