/*!
 * ZYMARG Algolia Search - Gutenberg block registration.
 * v1.0.6
 *
 * Adds a "ZYMARG Search" button to the WordPress block inserter and
 * exposes a full set of inspector controls (sidebar) so the user can
 * customize:
 *
 *   - Placeholder text
 *   - Max width / input height / font size / horizontal padding / icon size
 *   - Border radius
 *   - Dropdown max height / radius / offset
 *   - Text / placeholder / background / border / accent / dropdown bg colors
 *
 * Uses ServerSideRender so the search bar renders live in the editor
 * exactly as it will on the public page — no need to publish.
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
	var PanelColorSettings = blockEditor.PanelColorSettings;
	var TextControl       = components.TextControl;
	var RangeControl      = components.RangeControl;

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
			placeholder:        { type: 'string', default: '' },
			align:              { type: 'string' },

			maxWidth:           { type: 'number' },
			inputHeight:        { type: 'number' },
			fontSize:           { type: 'number' },
			borderRadius:       { type: 'number' },
			paddingX:           { type: 'number' },
			iconSize:           { type: 'number' },

			dropdownMaxHeight:  { type: 'number' },
			dropdownRadius:     { type: 'number' },
			dropdownOffset:     { type: 'number' },

			textColor:          { type: 'string' },
			placeholderColor:   { type: 'string' },
			bgColor:            { type: 'string' },
			borderColor:        { type: 'string' },
			accentColor:        { type: 'string' },
			dropdownBg:         { type: 'string' }
		},

		edit: function (props) {
			var atts    = props.attributes;
			var setAtts = props.setAttributes;

			function range(label, key, min, max, def, step, help) {
				if (!RangeControl) return null;
				return el(RangeControl, {
					label:    label,
					value:    typeof atts[key] === 'number' ? atts[key] : def,
					onChange: function (v) {
						var o = {}; o[key] = (typeof v === 'number') ? v : undefined;
						setAtts(o);
					},
					min:      min,
					max:      max,
					step:     step || 1,
					help:     help || undefined,
					allowReset: true
				});
			}

			var inspector = null;
			if (InspectorControls && PanelBody) {
				var contentPanel = el(
					PanelBody,
					{ title: __('Content', 'zymarg-algolia'), initialOpen: true },
					TextControl ? el(TextControl, {
						label:    __('Placeholder text', 'zymarg-algolia'),
						help:     __('Custom text shown inside the search bar. Leave empty for the default.', 'zymarg-algolia'),
						value:    atts.placeholder || '',
						onChange: function (val) { setAtts({ placeholder: val }); }
					}) : null
				);

				var layoutPanel = el(
					PanelBody,
					{ title: __('Search bar size', 'zymarg-algolia'), initialOpen: false },
					range(__('Max width (px)',        'zymarg-algolia'), 'maxWidth',     200, 1600, 720, 10,
						__('Set this large to make the bar fill its container.', 'zymarg-algolia')),
					range(__('Bar height (px)',       'zymarg-algolia'), 'inputHeight',   32,  100,  50, 1),
					range(__('Text size (px)',        'zymarg-algolia'), 'fontSize',      11,   28,  15, 1),
					range(__('Horizontal padding (px)','zymarg-algolia'), 'paddingX',      0,   40,  14, 1),
					range(__('Icon size (px)',        'zymarg-algolia'), 'iconSize',      12,   32,  18, 1),
					range(__('Border radius (px)',    'zymarg-algolia'), 'borderRadius',   0,   60,  14, 1)
				);

				var dropdownPanel = el(
					PanelBody,
					{ title: __('Results dropdown', 'zymarg-algolia'), initialOpen: false },
					range(__('Max height (px)',        'zymarg-algolia'), 'dropdownMaxHeight', 120, 900, 480, 10,
						__('How tall the dropdown grows before it scrolls.', 'zymarg-algolia')),
					range(__('Border radius (px)',     'zymarg-algolia'), 'dropdownRadius',      0,  40,  14, 1),
					range(__('Offset from bar (px)',   'zymarg-algolia'), 'dropdownOffset',      0,  30,   8, 1)
				);

				var colorsPanel = PanelColorSettings ? el(PanelColorSettings, {
					title: __('Colors', 'zymarg-algolia'),
					initialOpen: false,
					colorSettings: [
						{ value: atts.textColor,        onChange: function (v) { setAtts({ textColor: v }); },        label: __('Text color',        'zymarg-algolia') },
						{ value: atts.placeholderColor, onChange: function (v) { setAtts({ placeholderColor: v }); }, label: __('Placeholder color', 'zymarg-algolia') },
						{ value: atts.bgColor,          onChange: function (v) { setAtts({ bgColor: v }); },          label: __('Background color', 'zymarg-algolia') },
						{ value: atts.borderColor,      onChange: function (v) { setAtts({ borderColor: v }); },      label: __('Border color',     'zymarg-algolia') },
						{ value: atts.accentColor,      onChange: function (v) { setAtts({ accentColor: v }); },      label: __('Accent color',     'zymarg-algolia') },
						{ value: atts.dropdownBg,       onChange: function (v) { setAtts({ dropdownBg: v }); },       label: __('Dropdown background','zymarg-algolia') }
					]
				}) : null;

				inspector = el(
					InspectorControls,
					null,
					contentPanel,
					layoutPanel,
					dropdownPanel,
					colorsPanel
				);
			}

			var preview;
			if (ServerSideRender) {
				preview = el(ServerSideRender, {
					block:      'zymarg/algolia-search',
					attributes: atts
				});
			} else {
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
