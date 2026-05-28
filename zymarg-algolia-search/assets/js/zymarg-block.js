/*!
 * ZYMARG Algolia Search - Gutenberg block registration.
 * v1.0.5
 *
 * Adds a "ZYMARG Search" button to the WordPress block inserter.
 * Uses ServerSideRender so the search bar shows up live in the editor — no
 * need to publish to see how it looks. Editing the Placeholder in the
 * sidebar updates the preview instantly.
 */
(function (wp) {
	if (!wp || !wp.blocks || !wp.element) return;

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var blockEditor       = wp.blockEditor || wp.editor || {};
	var components        = wp.components || {};
	var i18n              = wp.i18n || {};

	var __ = (typeof i18n.__ === 'function') ? i18n.__ : function (s) { return s; };

	// ServerSideRender lives in different places across WP versions.
	var ServerSideRender = wp.serverSideRender;
	if (ServerSideRender && ServerSideRender.default) {
		ServerSideRender = ServerSideRender.default;
	}
	if (!ServerSideRender && components.ServerSideRender) {
		ServerSideRender = components.ServerSideRender;
	}

	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody         = components.PanelBody;
	var TextControl       = components.TextControl;

	registerBlockType('zymarg/algolia-search', {
		apiVersion:  2,
		title:       __('ZYMARG Search', 'zymarg-algolia'),
		description: __('Algolia-powered instant search bar for products, vendors and categories. Drop it anywhere — no shortcode required.', 'zymarg-algolia'),
		category:    'widgets',
		icon:        'search',
		keywords: [
			__('search',  'zymarg-algolia'),
			__('algolia', 'zymarg-algolia'),
			__('zymarg',  'zymarg-algolia'),
			__('product', 'zymarg-algolia'),
			__('finder',  'zymarg-algolia')
		],
		supports: {
			html:  false,
			align: ['wide', 'full']
		},
		attributes: {
			placeholder: { type: 'string', default: '' },
			align:       { type: 'string' }
		},

		edit: function (props) {
			var inspector = null;
			if (InspectorControls && PanelBody && TextControl) {
				inspector = el(
					InspectorControls, null,
					el(
						PanelBody,
						{ title: __('Search Settings', 'zymarg-algolia'), initialOpen: true },
						el(TextControl, {
							label:       __('Placeholder text', 'zymarg-algolia'),
							help:        __('Custom text shown inside the search bar. Leave empty for the default.', 'zymarg-algolia'),
							value:       props.attributes.placeholder || '',
							onChange:    function (val) { props.setAttributes({ placeholder: val }); }
						})
					)
				);
			}

			var preview;
			if (ServerSideRender) {
				preview = el(ServerSideRender, {
					block:      'zymarg/algolia-search',
					attributes: props.attributes
				});
			} else {
				// Last-ditch fallback if ServerSideRender isn't available.
				preview = el(
					'div',
					{ className: 'zymarg-algolia-block-fallback', style: {
						padding: '12px 16px',
						border: '1px dashed #c3aaff',
						borderRadius: '10px',
						color: '#7B3FE4',
						fontWeight: 600,
						textAlign: 'center'
					} },
					__('ZYMARG Search bar — preview will render on the public page.', 'zymarg-algolia')
				);
			}

			return el(Fragment, null, inspector, preview);
		},

		// Dynamic block — rendered server-side via render_callback.
		save: function () { return null; }
	});
})(window.wp);
