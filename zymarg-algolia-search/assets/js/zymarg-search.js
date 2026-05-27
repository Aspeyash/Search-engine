/*!
 * ZYMARG Algolia Search - Frontend instant search
 * Renders a multi-index dropdown (products / vendors / categories) using
 * Algolia's lite client + InstantSearch.js. No page reload.
 */
(function () {
	'use strict';

	if (typeof window.algoliasearch !== 'function' || typeof window.instantsearch !== 'function') {
		// Library not loaded yet — try after window load.
		window.addEventListener('load', boot);
		return;
	}
	boot();

	function boot() {
		var cfg = window.ZymargAlgolia;
		if (!cfg || !cfg.appId || !cfg.searchKey) {
			return;
		}
		var wrappers = document.querySelectorAll('[data-zymarg-search]');
		if (!wrappers.length) return;

		Array.prototype.forEach.call(wrappers, function (wrapper) {
			if (wrapper.__zymargBooted) return;
			wrapper.__zymargBooted = true;
			initWrapper(wrapper, cfg);
		});
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
			// Algolia already wraps in <mark> via highlightPreTag/PostTag.
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

	function initWrapper(wrapper, cfg) {
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

		var openDropdown = function () { dropdown.hidden = false; };
		var closeDropdown = function () {
			dropdown.hidden = true;
			emptyBox.hidden = true;
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

			client.search(requests).then(function (res) {
				hideLoading();
				var p = res.results[0] || {};
				var v = res.results[1] || {};
				var c = res.results[2] || {};
				var pHits = p.hits || [];
				var vHits = v.hits || [];
				var cHits = c.hits || [];
				if (!pHits.length && !vHits.length && !cHits.length) {
					renderEmpty();
					return;
				}
				renderResults(pHits, vHits, cHits, query);
			}).catch(function (err) {
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
			if (q) search(q);
		});

		// Submit -> let WP standard search take over (SEO crawlable).
		if (form) {
			form.addEventListener('submit', function () {
				closeDropdown();
				// Append post_type=product so WC search page is hit.
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
			if (e.key === 'Escape') {
				closeDropdown();
				input.blur();
			}
		});
	}
})();
