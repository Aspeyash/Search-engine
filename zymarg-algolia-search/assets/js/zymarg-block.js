/*!
 * ZYMARG Algolia Search - Gutenberg block registration.
 * v1.0.7
 *
 * Adds a "ZYMARG Search" button to the WordPress block inserter and
 * exposes a comprehensive set of inspector controls (sidebar) so the user
 * can customize:
 *
 *   - Placeholder text
 *   - Stretch to full container width (toggle)
 *   - Max width / bar height / text size / horizontal padding /
 *     icon size / border radius
 *   - Input field internals: vertical text padding, line height,
 *     min-width
 *   - Dropdown max height / radius / offset
 *   - Text / placeholder / background / border / accent /
 *     dropdown bg colors
 *
 * Uses ServerSideRender so the search bar renders live in the editor
 * exactly as it will on the public page.
 */
;(function (wp) {
	if (!wp || !wp.blocks || !wp.element) return;

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var blockEditor       = wp.blockEditor || wp.editor || {};
	var components        = wp.components || {};
	var i18n              = wp.i18n || {};

	var __ = (typeof i18n.__ === 'function') ? i18n.__ : function (s) { return s; };

	var ServerSideRender = wp.serverSideRender;
	if (ServerSideRender && ServerSideRender.default) {
		ServerSideRender = ServerSideRender.default;
	}
	if (!ServerSideRender && components.ServerSideRender) {
		ServerSideRender = components.ServerSideRender;
	}

	var InspectorControls  = blockEditor.InspectorControls;
	var PanelBody          = components.PanelBody;
	var PanelColorSettings = blockEditor.PanelColorSettings;
	var TextControl        = components.TextControl;
	var RangeControl       = components.RangeControl;
	var ToggleControl      = components.ToggleControl;

	registerBlockType('zymarg/algolia-search', {
		apiVersion:  2,
		title:       __('ZYMARG Search', 'zymarg-algolia'),
		description: __('Algolia-powered instant search bar for products, vendors and categories.', 'zymarg-algolia'),
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
			placeholder:        { type: 'string',  default: '' },
			align:              { type: 'string' },

			stretch:            { type: 'boolean', default: false },
			showDropdown:       { type: 'boolean', default: true },
			showEmpty:          { type: 'boolean', default: true },
			showClear:          { type: 'boolean', default: true },
			clearLeft:          { type: 'boolean', default: false },
			showProducts:       { type: 'boolean', default: true },
			showCategories:     { type: 'boolean', default: true },
			showVendors:        { type: 'boolean', default: false },
			maxWidth:           { type: 'number' },
			inputHeight:        { type: 'number' },
			fontSize:           { type: 'number' },
			borderRadius:       { type: 'number' },
			paddingX:           { type: 'number' },
			iconSize:           { type: 'number' },

			inputPaddingY:      { type: 'number' },
			inputMinWidth:      { type: 'number' },

			dropdownMaxHeight:  { type: 'number' },
			dropdownRadius:     { type: 'number' },
			dropdownOffset:     { type: 'number' },

			emptyTextSize:      { type: 'number' },
			emptyBtnSize:       { type: 'number' },

			clearSize:          { type: 'number' },
			clearIconSize:      { type: 'number' },
			clearRadius:        { type: 'number' },
			clearGap:           { type: 'number' },
			clearEdge:          { type: 'number' },

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
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Show Products section', 'zymarg-algolia'),
						help:     __('Renders the Products section in the dropdown. Render order: Products → Categories → Vendors.', 'zymarg-algolia'),
						checked:  atts.showProducts !== false,
						onChange: function (v) { setAtts({ showProducts: !!v }); }
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Show Categories section', 'zymarg-algolia'),
						help:     __('Renders the Categories section after Products.', 'zymarg-algolia'),
						checked:  atts.showCategories !== false,
						onChange: function (v) { setAtts({ showCategories: !!v }); }
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Show Vendors section', 'zymarg-algolia'),
						help:     __('Default OFF. When OFF, the plugin skips the Algolia call to zymarg_vendors entirely.', 'zymarg-algolia'),
						checked:  !!atts.showVendors,
						onChange: function (v) { setAtts({ showVendors: !!v }); }
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Show results dropdown', 'zymarg-algolia'),
						help:     __('When OFF the live results dropdown is hidden — bar behaves like a plain WP search form (type, then press Enter).', 'zymarg-algolia'),
						checked:  atts.showDropdown !== false,
						onChange: function (v) { setAtts({ showDropdown: !!v }); }
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Show empty message', 'zymarg-algolia'),
						help:     __('When OFF the "Couldn\'t find what you\'re looking for? Request Here" CTA is hidden — when zero results match, the dropdown closes silently.', 'zymarg-algolia'),
						checked:  atts.showEmpty !== false,
						onChange: function (v) { setAtts({ showEmpty: !!v }); }
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Show clear (×) button', 'zymarg-algolia'),
						help:     __('Turn OFF to never display the X clear button. Customize size / position / colors in the "Clear button" panel below.', 'zymarg-algolia'),
						checked:  atts.showClear !== false,
						onChange: function (v) { setAtts({ showClear: !!v }); }
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Place X on the left side', 'zymarg-algolia'),
						help:     __('Move the clear button to the left of the input instead of the default right side.', 'zymarg-algolia'),
						checked:  !!atts.clearLeft,
						onChange: function (v) { setAtts({ clearLeft: !!v }); }
					}) : null,
					ToggleControl ? el(ToggleControl, {
						label:    __('Stretch to full container width', 'zymarg-algolia'),
						help:     __('Fills 100% of the immediate parent.', 'zymarg-algolia'),
						checked:  !!atts.stretch,
						onChange: function (v) { setAtts({ stretch: !!v }); }
					}) : null
				);

				var layoutPanel = el(
					PanelBody,
					{ title: __('Search bar size', 'zymarg-algolia'), initialOpen: false },
					range(__('Max width (px)',         'zymarg-algolia'), 'maxWidth',     200, 5000, 720, 10,
						__('Up to 5000px. Ignored when "Stretch" or "Full screen width" is ON.', 'zymarg-algolia')),
					range(__('Bar height (px)',        'zymarg-algolia'), 'inputHeight',   32,  120,  50, 1),
					range(__('Horizontal padding (px)','zymarg-algolia'), 'paddingX',       0,   60,  14, 1),
					range(__('Icon size (px)',         'zymarg-algolia'), 'iconSize',       0,   40,  18, 1),
					range(__('Border radius (px)',     'zymarg-algolia'), 'borderRadius',   0,   80,  14, 1)
				);

				var inputPanel = el(
					PanelBody,
					{ title: __('Input field (text area)', 'zymarg-algolia'), initialOpen: false },
					range(__('Text size (px)',          'zymarg-algolia'), 'fontSize',       11,  40, 15, 1),
					range(__('Vertical text padding (px)','zymarg-algolia'), 'inputPaddingY', 0,  40,  0, 1,
						__('Extra space above and below the typed text inside the input.', 'zymarg-algolia')),
					range(__('Input min-width (px)',    'zymarg-algolia'), 'inputMinWidth',   0,1200,  0, 10,
						__('Force the typing area to a minimum width.', 'zymarg-algolia'))
				);

				var dropdownPanel = el(
					PanelBody,
					{ title: __('Results dropdown', 'zymarg-algolia'), initialOpen: false },
					range(__('Max height (px)',        'zymarg-algolia'), 'dropdownMaxHeight', 120, 1200, 480, 10),
					range(__('Border radius (px)',     'zymarg-algolia'), 'dropdownRadius',      0,   60,  14, 1),
					range(__('Offset from bar (px)',   'zymarg-algolia'), 'dropdownOffset',      0,   50,   8, 1)
				);

				var emptyPanel = el(
					PanelBody,
					{ title: __('Empty state ("Couldn\'t find...")', 'zymarg-algolia'), initialOpen: false },
					range(__('Message text size (px)', 'zymarg-algolia'), 'emptyTextSize',      10,   32,  14, 1),
					range(__('Button text size (px)',  'zymarg-algolia'), 'emptyBtnSize',       10,   32,  14, 1)
				);

				var clearPanel = el(
					PanelBody,
					{ title: __('Clear button (×)', 'zymarg-algolia'), initialOpen: false },
					range(__('Button size (px)',     'zymarg-algolia'), 'clearSize',     12, 70, 26, 1,
						__('Width and height of the round button. Small to big.', 'zymarg-algolia')),
					range(__('Icon size (px)',       'zymarg-algolia'), 'clearIconSize',  6, 40, 14, 1,
						__('Size of the X inside the button.', 'zymarg-algolia')),
					range(__('Border radius (px)',   'zymarg-algolia'), 'clearRadius',    0, 40, 13, 1,
						__('Drag low for a square, high for a circle.', 'zymarg-algolia')),
					range(__('Space from input (px)','zymarg-algolia'), 'clearGap',       0, 40,  0, 1,
						__('Margin between the X button and the typed text.', 'zymarg-algolia')),
					range(__('Distance from edge (px)','zymarg-algolia'), 'clearEdge',    0, 40,  0, 1,
						__('Extra margin between the X and the bar edge.', 'zymarg-algolia'))
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
					inputPanel,
					dropdownPanel,
					emptyPanel,
					clearPanel,
					colorsPanel
				);
			}

			// Pass attributes through to ServerSideRender as-is.
			var renderAtts = atts;

			var preview;
			if (ServerSideRender) {
				preview = el(ServerSideRender, {
					block:      'zymarg/algolia-search',
					attributes: renderAtts
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

		save: function () { return null; }
	});
})(window.wp);
