/*!
 * ZYMARG Algolia Search - Frontend instant search (v2.0.0)
 * Renders a multi-index dropdown (products / vendors / categories) using
 * Algolia's lite client. No page reload.
 *
 * Search Engine 2.0 features (each gated by a flag from settings):
 *   - fast        : in-memory cache + request-sequence guard (no stale flicker)
 *   - keyboard    : Up/Down/Enter navigation using the existing .is-active style
 *   - recent      : recent searches stored in the visitor's own browser
 *   - insights    : Algolia Insights click events (opt-in)
 *   - logNoResults: report zero-result queries to the server (admin-ajax)
 *   - suggestions : as-you-type Query Suggestions from a dedicated index
 *
 * The dropdown animation and the SEO-safe ?s= form submit are intentionally
 * left untouched.
 */
(function () {
	'use strict';

	var RECENT_KEY = 'zymargRecentSearches';
	var RECENT_MAX = 6;

	// Only the Algolia lite client is required now (InstantSearch.js removed).
	if (typeof window.algoliasearch !== 'function') {
		window.addEventListener('load', boot);
		return;
	}
	boot();

	function boot() {
		var cfg = window.ZymargAlgolia;
		if (!cfg || !cfg.appId || !cfg.searchKey) {
			return;
		}
		cfg.features = cfg.features || {};

		var insights = initInsights(cfg);

		var wrappers = document.querySelectorAll('[data-zymarg-search]');
		if (!wrappers.length) return;

		Array.prototype.forEach.call(wrappers, function (wrapper) {
			if (wrapper.__zymargBooted) return;
			wrapper.__zymargBooted = true;
			initWrapper(wrapper, cfg, insights);
		});
	}

	/* -------------------------------------------------------------------- */
	/* Helpers.                                                             */
	/* -------------------------------------------------------------------- */

	function initInsights(cfg) {
		if (!cfg.features.insights) return null;
		if (typeof window.aa !== 'function') return null;
		try {
			window.aa('init', {
				appId: cfg.appId,
				apiKey: cfg.searchKey,
				useCookie: true
			});
			return window.aa;
		} catch (e) {
			return null;
		}
	}

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

	function getRecent() {
		try {
			var v = JSON.parse(window.localStorage.getItem(RECENT_KEY));
			return Array.isArray(v) ? v : [];
		} catch (e) {
			return [];
		}
	}

	function addRecent(q) {
		q = (q || '').trim();
		if (!q) return;
		try {
			var list = getRecent().filter(function (x) {
				return x.toLowerCase() !== q.toLowerCase();
			});
			list.unshift(q);
			list = list.slice(0, RECENT_MAX);
			window.localStorage.setItem(RECENT_KEY, JSON.stringify(list));
		} catch (e) {}
	}

	function clearRecent() {
		try { window.localStorage.removeItem(RECENT_KEY); } catch (e) {}
	}

	/* -------------------------------------------------------------------- */
	/* Per-wrapper init.                                                    */
	/* -------------------------------------------------------------------- */

	function initWrapper(wrapper, cfg, insights) {
		var feat = cfg.features;
		var input = wrapper.querySelector('.zymarg-algolia-input');
		var dropdown = wrapper.querySelector('.zymarg-algolia-dropdown');
		var resultsBox = wrapper.querySelector('.zymarg-algolia-results');
		var emptyBox = wrapper.querySelector('.zymarg-algolia-empty');
		var loadingBox = wrapper.querySelector('.zymarg-algolia-loading');
		var clearBtn = wrapper.querySelector('.zymarg-algolia-clear');
		var form = wrapper.querySelector('.zymarg-algolia-form');

		if (!input || !dropdown) return;

		// Empty state content.
		var emptyText = emptyBox.querySelector('.zymarg-algolia-empty-text');
		var emptyBtn = emptyBox.querySelector('.zymarg-algolia-empty-btn');
		if (emptyText) emptyText.textContent = cfg.noResultsText || "Couldn't find what you're looking for?";
		if (emptyBtn) {
			emptyBtn.textContent = cfg.requestBtn || 'Request Here';
			emptyBtn.setAttribute('href', cfg.communityUrl || '/community');
		}

		var client = window.algoliasearch(cfg.appId, cfg.searchKey);

		// State for the smart features.
		var cache = {};        // query -> payload (feat.fast)
		var seq = 0;           // request-sequence guard (feat.fast)
		var loggedQueries = {};// dedupe no-results logging
		var lastQueryId = null;// Insights queryID
		var activeIndex = -1;  // keyboard nav

		var openDropdown = function () { dropdown.hidden = false; };
		var closeDropdown = function () {
			dropdown.hidden = true;
			emptyBox.hidden = true;
			loadingBox.hidden = true;
			activeIndex = -1;
		};
		var showLoading = function () { loadingBox.hidden = false; };
		var hideLoading = function () { loadingBox.hidden = true; };

		/* ---------- No-results logging (server side) ---------- */
		var logNoResults = function (query) {
			if (!feat.logNoResults || !query || !cfg.ajaxUrl) return;
			var key = query.toLowerCase();
			if (loggedQueries[key]) return;
			loggedQueries[key] = 1;
			try {
				var body = 'action=zymarg_algolia_log_no_results' +
					'&nonce=' + encodeURIComponent(cfg.logNonce || '') +
					'&query=' + encodeURIComponent(query);
				fetch(cfg.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body,
					keepalive: true
				});
			} catch (e) {}
		};

		/* ---------- Keyboard navigation ---------- */
		var selectableItems = function () {
			return dropdown.querySelectorAll(
				'.zymarg-algolia-hit, .zymarg-algolia-viewall, .zymarg-algolia-suggestion, .zymarg-algolia-recent-item'
			);
		};
		var setActive = function (idx) {
			var items = selectableItems();
			if (!items.length) { activeIndex = -1; return; }
			// Remove previous.
			Array.prototype.forEach.call(items, function (el) {
				el.classList.remove('is-active');
			});
			if (idx < 0) idx = items.length - 1;
			if (idx >= items.length) idx = 0;
			activeIndex = idx;
			var el = items[activeIndex];
			el.classList.add('is-active');
			if (el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
		};

		/* ---------- Recent searches ---------- */
		var renderRecent = function () {
			if (!feat.recent) { closeDropdown(); return; }
			var list = getRecent();
			if (!list.length) { closeDropdown(); return; }
			emptyBox.hidden = true;
			var label = (cfg.i18nExtra && cfg.i18nExtra.recent) || 'Recent searches';
			var clearTxt = (cfg.i18nExtra && cfg.i18nExtra.clearRecent) || 'Clear';
			var html = '<div class="zymarg-algolia-section">' +
				'<div class="zymarg-algolia-section-head">' +
					'<h4 class="zymarg-algolia-section-title">' + escapeHtml(label) + '</h4>' +
					'<button type="button" class="zymarg-algolia-clear-recent">' + escapeHtml(clearTxt) + '</button>' +
				'</div>';
			list.forEach(function (q) {
				html += '<button type="button" class="zymarg-algolia-hit zymarg-algolia-recent-item" data-recent-q="' + escapeHtml(q) + '">' +
					'<span class="zymarg-algolia-mini-icon" aria-hidden="true">&#8634;</span>' +
					'<span class="zymarg-algolia-hit-body"><span class="zymarg-algolia-hit-title">' + escapeHtml(q) + '</span></span>' +
				'</button>';
			});
			html += '</div>';
			resultsBox.innerHTML = html;
			activeIndex = -1;
			openDropdown();
		};

		/* ---------- Empty (no results) ---------- */
		var renderEmpty = function (query) {
			resultsBox.innerHTML = '';
			emptyBox.hidden = false;
			activeIndex = -1;
			openDropdown();
			logNoResults(query);
		};

		/* ---------- Results ---------- */
		var renderResults = function (productHits, vendorHits, catHits, suggestHits, query) {
			emptyBox.hidden = true;
			var html = '';

			// Query Suggestions (top).
			if (feat.suggestions && suggestHits && suggestHits.length) {
				var sLabel = (cfg.i18nExtra && cfg.i18nExtra.suggestions) || 'Suggestions';
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(sLabel) + '</h4>';
				suggestHits.slice(0, 5).forEach(function (h) {
					var q = h.query || h.name || '';
					if (!q) return;
					html += '<button type="button" class="zymarg-algolia-hit zymarg-algolia-suggestion" data-suggest-q="' + escapeHtml(q) + '">' +
						'<span class="zymarg-algolia-mini-icon" aria-hidden="true">&#8599;</span>' +
						'<span class="zymarg-algolia-hit-body"><span class="zymarg-algolia-hit-title">' + getHighlight(h, 'query') + '</span></span>' +
					'</button>';
				});
				html += '</div>';
			}

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

			if (productHits && productHits.length) {
				html += '<div class="zymarg-algolia-section"><h4 class="zymarg-algolia-section-title">' +
					escapeHtml(cfg.i18n.products) + '</h4>';
				productHits.slice(0, 6).forEach(function (h, i) {
					var price = h.price_html
						? h.price_html
						: (h.price ? (cfg.currencySym + Number(h.price).toFixed(2)) : '');
					var vendor = h.vendor_name
						? '<span class="zymarg-algolia-hit-meta">' + escapeHtml(cfg.i18n.by) + ' ' +
							getHighlight(h, 'vendor_name') + '</span>'
						: '';

					// Insights data attributes (only emitted when enabled + objectID present).
					var insAttr = '';
					if (feat.insights && h.objectID) {
						insAttr = ' data-object-id="' + escapeHtml(h.objectID) + '"' +
							' data-position="' + (i + 1) + '"' +
							' data-index="' + escapeHtml(cfg.indexProducts) + '"';
					}

					html += '<a class="zymarg-algolia-hit" href="' + escapeHtml(h.permalink || '#') + '"' + insAttr + '>' +
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

			// "See all" link -> standard search page (?s=) so SEO crawl works.
			if (query) {
				var url = (form && form.getAttribute('action')) || '/';
				url += (url.indexOf('?') >= 0 ? '&' : '?') + 's=' + encodeURIComponent(query) +
					'&post_type=product';
				html += '<a class="zymarg-algolia-viewall" href="' + escapeHtml(url) + '">' +
					escapeHtml(cfg.i18n.viewAll) + ' &rarr;</a>';
			}

			resultsBox.innerHTML = html;
			activeIndex = -1;
			openDropdown();
		};

		/* ---------- Apply a result payload ---------- */
		var applyResults = function (payload, query) {
			hideLoading();
			lastQueryId = payload.queryID || null;
			if (!payload.p.length && !payload.v.length && !payload.c.length) {
				renderEmpty(query);
				return;
			}
			renderResults(payload.p, payload.v, payload.c, payload.s, query);
		};

		/* ---------- Search ---------- */
		var search = function (query) {
			if (!query) {
				if (feat.recent) { renderRecent(); } else { closeDropdown(); }
				return;
			}

			// Serve from cache instantly.
			if (feat.fast && cache[query]) {
				applyResults(cache[query], query);
				return;
			}

			var my = ++seq;
			showLoading();
			openDropdown();

			var productParams = { query: query, hitsPerPage: 6 };
			if (feat.insights) {
				productParams.clickAnalytics = true;
			}

			var requests = [
				{ indexName: cfg.indexProducts, params: productParams },
				{ indexName: cfg.indexVendors,  params: { query: query, hitsPerPage: 4 } },
				{ indexName: cfg.indexCats,     params: { query: query, hitsPerPage: 3 } }
			];

			var suggIdx = -1;
			if (feat.suggestions && cfg.suggestionsIndex) {
				suggIdx = requests.length;
				requests.push({
					indexName: cfg.suggestionsIndex,
					params: { query: query, hitsPerPage: 5 }
				});
			}

			client.search(requests).then(function (res) {
				// Ignore out-of-order responses (only when fast mode is on).
				if (feat.fast && my !== seq) return;

				var p = res.results[0] || {};
				var v = res.results[1] || {};
				var c = res.results[2] || {};
				var payload = {
					p: p.hits || [],
					v: v.hits || [],
					c: c.hits || [],
					s: (suggIdx >= 0 && res.results[suggIdx]) ? (res.results[suggIdx].hits || []) : [],
					queryID: p.queryID || null
				};

				if (feat.fast) cache[query] = payload;
				applyResults(payload, query);
			}).catch(function (err) {
				if (feat.fast && my !== seq) return;
				hideLoading();
				if (window.console) console.error('[ZymargAlgolia]', err);
				closeDropdown();
			});
		};

		var debounced = debounce(function () {
			var q = (input.value || '').trim();
			if (clearBtn) clearBtn.hidden = !q;
			search(q);
		}, 120);

		input.addEventListener('input', debounced);

		input.addEventListener('focus', function () {
			var q = (input.value || '').trim();
			if (q) {
				search(q);
			} else if (feat.recent) {
				renderRecent();
			}
		});

		/* ---------- Keyboard navigation + Esc ---------- */
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closeDropdown();
				input.blur();
				return;
			}
			if (!feat.keyboard || dropdown.hidden) {
				return;
			}
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				setActive(activeIndex + 1);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				setActive(activeIndex - 1);
			} else if (e.key === 'Enter') {
				var items = selectableItems();
				if (activeIndex >= 0 && items[activeIndex]) {
					// Follow the highlighted item instead of submitting the form.
					e.preventDefault();
					items[activeIndex].click();
				}
				// Otherwise: let the form submit -> ?s= results page (unchanged).
			}
		});

		/* ---------- Submit -> WP standard search (SEO crawlable, unchanged) ---------- */
		if (form) {
			form.addEventListener('submit', function () {
				addRecent((input.value || '').trim());
				closeDropdown();
				var hidden = form.querySelector('input[name="post_type"]');
				if (!hidden) {
					hidden = document.createElement('input');
					hidden.type = 'hidden';
					hidden.name = 'post_type';
					hidden.value = 'product';
					form.appendChild(hidden);
				}
			});
		}

		/* ---------- Delegated clicks inside the dropdown ---------- */
		dropdown.addEventListener('click', function (e) {
			// Clear recent searches.
			if (e.target.closest('.zymarg-algolia-clear-recent')) {
				e.preventDefault();
				clearRecent();
				closeDropdown();
				input.focus();
				return;
			}

			// Recent search chosen.
			var recentEl = e.target.closest('.zymarg-algolia-recent-item');
			if (recentEl) {
				e.preventDefault();
				var rq = recentEl.getAttribute('data-recent-q') || '';
				input.value = rq;
				if (clearBtn) clearBtn.hidden = !rq;
				search(rq);
				input.focus();
				return;
			}

			// Suggestion chosen.
			var suggEl = e.target.closest('.zymarg-algolia-suggestion');
			if (suggEl) {
				e.preventDefault();
				var sq = suggEl.getAttribute('data-suggest-q') || '';
				input.value = sq;
				if (clearBtn) clearBtn.hidden = !sq;
				search(sq);
				input.focus();
				return;
			}

			// Insights: product click after search.
			if (insights && lastQueryId) {
				var hit = e.target.closest('[data-object-id]');
				if (hit) {
					try {
						insights('clickedObjectIDsAfterSearch', {
							index: hit.getAttribute('data-index'),
							eventName: 'Product Clicked',
							queryID: lastQueryId,
							objectIDs: [hit.getAttribute('data-object-id')],
							positions: [parseInt(hit.getAttribute('data-position'), 10) || 1]
						});
					} catch (err) {}
				}
			}

			// Any real result link counts as a "recent search".
			if (e.target.closest('a.zymarg-algolia-hit, .zymarg-algolia-viewall')) {
				addRecent((input.value || '').trim());
			}
		});

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				clearBtn.hidden = true;
				if (feat.recent) { renderRecent(); } else { closeDropdown(); }
				input.focus();
			});
		}

		// Click outside -> close.
		document.addEventListener('click', function (e) {
			if (!wrapper.contains(e.target)) closeDropdown();
		});
	}
})();
