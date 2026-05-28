<?php
/**
 * Elementor widget: "ZYMARG Search".
 *
 * v1.0.7 changes:
 *   - "Stretch to full container width" toggle (overrides max-width entirely)
 *   - Max width slider now goes up to 3000px (was capped at 1600px)
 *   - New "Input field" section with explicit Field height, Vertical text
 *     padding, Line height, Min width controls — covers the "tab where I
 *     write the word" feedback.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class Zymarg_Algolia_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'zymarg_algolia_search';
	}

	public function get_title() {
		return __( 'ZYMARG Search', 'zymarg-algolia' );
	}

	public function get_icon() {
		return 'eicon-site-search';
	}

	public function get_categories() {
		return array( 'zymarg', 'general' );
	}

	public function get_keywords() {
		return array( 'search', 'algolia', 'zymarg', 'product', 'instant', 'autocomplete' );
	}

	public function get_script_depends() {
		return array( Zymarg_Algolia_Frontend::SCRIPT_HANDLE );
	}

	public function get_style_depends() {
		return array( Zymarg_Algolia_Frontend::STYLE_HANDLE );
	}

	protected function register_controls() {

		/* ============================================================ */
		/* Content tab.                                                  */
		/* ============================================================ */

		$this->start_controls_section(
			'section_search',
			array(
				'label' => __( 'Search', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'placeholder',
			array(
				'label'       => __( 'Placeholder text', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Search products, vendors, categories…', 'zymarg-algolia' ),
				'description' => __( 'Leave empty to use the default placeholder.', 'zymarg-algolia' ),
				'label_block' => true,
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array( 'title' => __( 'Left',   'zymarg-algolia' ), 'icon' => 'eicon-text-align-left' ),
					'center'     => array( 'title' => __( 'Center', 'zymarg-algolia' ), 'icon' => 'eicon-text-align-center' ),
					'flex-end'   => array( 'title' => __( 'Right',  'zymarg-algolia' ), 'icon' => 'eicon-text-align-right' ),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}}' => 'display:flex; justify-content: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'show_dropdown',
			array(
				'label'        => __( 'Show results dropdown', 'zymarg-algolia' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'description'  => __( 'When OFF the live results dropdown is hidden — the bar then behaves like a plain WP search form (type, then press Enter to go to the search results page).', 'zymarg-algolia' ),
				'label_on'     => __( 'On', 'zymarg-algolia' ),
				'label_off'    => __( 'Off', 'zymarg-algolia' ),
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_products_section',
			array(
				'label'        => __( 'Show Products section', 'zymarg-algolia' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'description'  => __( 'When OFF the Products section is removed from the dropdown.', 'zymarg-algolia' ),
				'label_on'     => __( 'On', 'zymarg-algolia' ),
				'label_off'    => __( 'Off', 'zymarg-algolia' ),
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_categories_section',
			array(
				'label'        => __( 'Show Categories section', 'zymarg-algolia' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'description'  => __( 'When OFF the Categories section is removed from the dropdown. Render order is Products → Categories.', 'zymarg-algolia' ),
				'label_on'     => __( 'On', 'zymarg-algolia' ),
				'label_off'    => __( 'Off', 'zymarg-algolia' ),
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_vendors_section',
			array(
				'label'        => __( 'Show Vendors section', 'zymarg-algolia' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'description'  => __( 'When OFF the Vendors section is hidden AND the plugin skips the Algolia call to zymarg_vendors entirely. Default OFF.', 'zymarg-algolia' ),
				'label_on'     => __( 'On', 'zymarg-algolia' ),
				'label_off'    => __( 'Off', 'zymarg-algolia' ),
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'stretch',
			array(
				'label'        => __( 'Stretch to full container width', 'zymarg-algolia' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'description'  => __( 'When ON, the bar ignores Max width and fills 100% of its immediate parent container.', 'zymarg-algolia' ),
				'label_on'     => __( 'On', 'zymarg-algolia' ),
				'label_off'    => __( 'Off', 'zymarg-algolia' ),
				'default'      => '',
				'return_value' => 'yes',
				'selectors'    => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => 'max-width: 100% !important;',
				),
			)
		);

		$this->add_responsive_control(
			'max_width',
			array(
				'label'       => __( 'Max width', 'zymarg-algolia' ),
				'description' => __( 'Goes up to 5000px. Ignored if "Stretch" is ON.', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%', 'vw' ),
				'range'       => array(
					'px' => array( 'min' => 200, 'max' => 5000, 'step' => 10 ),
					'%'  => array( 'min' => 30,  'max' => 100,  'step' => 1 ),
					'vw' => array( 'min' => 30,  'max' => 100,  'step' => 1 ),
				),
				'default'     => array( 'size' => 720, 'unit' => 'px' ),
				'condition'   => array(
					'stretch!' => 'yes',
				),
				'selectors'   => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Search bar (outer container).                     */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_bar',
			array(
				'label' => __( 'Search bar', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'input_height',
			array(
				'label'      => __( 'Bar height', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 32, 'max' => 120, 'step' => 1 ) ),
				'default'    => array( 'size' => 50, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-input-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'padding_x',
			array(
				'label'      => __( 'Horizontal padding', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				'default'    => array( 'size' => 14, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-padding-x: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => __( 'Border radius', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
				'default'    => array( 'size' => 14, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'border_width',
			array(
				'label'      => __( 'Border width', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 8, 'step' => 0.5 ) ),
				'default'    => array( 'size' => 1.5, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-border-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => __( 'Icon size', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				'default'    => array( 'size' => 18, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'icon_gap',
			array(
				'label'       => __( 'Icon gap', 'zymarg-algolia' ),
				'description' => __( 'Space between the icon and the text input.', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
				'default'     => array( 'size' => 10, 'unit' => 'px' ),
				'selectors'   => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-icon-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Input field — the typing area.                    */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_input',
			array(
				'label' => __( 'Input field (text area)', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'font_size',
			array(
				'label'      => __( 'Text size', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 11, 'max' => 40, 'step' => 1 ) ),
				'default'    => array( 'size' => 15, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'font_weight',
			array(
				'label'   => __( 'Text weight', 'zymarg-algolia' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '400',
				'options' => array(
					'300' => __( 'Light (300)',     'zymarg-algolia' ),
					'400' => __( 'Normal (400)',    'zymarg-algolia' ),
					'500' => __( 'Medium (500)',    'zymarg-algolia' ),
					'600' => __( 'Semi-bold (600)', 'zymarg-algolia' ),
					'700' => __( 'Bold (700)',      'zymarg-algolia' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-font-weight: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'input_padding_y',
			array(
				'label'       => __( 'Vertical text padding', 'zymarg-algolia' ),
				'description' => __( 'Extra space above and below the typed text inside the input.', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				'default'     => array( 'size' => 0, 'unit' => 'px' ),
				'selectors'   => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-input-padding-y: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'input_line_height',
			array(
				'label'      => __( 'Text line height', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::HIDDEN,
				'default'    => '',
				'description' => __( 'Removed in 1.0.11 — input elements ignore line-height when they have a fixed bar height. Use Bar height + Vertical text padding instead.', 'zymarg-algolia' ),
			)
		);

		$this->add_responsive_control(
			'input_min_width',
			array(
				'label'       => __( 'Input min-width', 'zymarg-algolia' ),
				'description' => __( 'Force the typing area to a minimum width — useful when the icon + clear button take up too much space.', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 1200, 'step' => 10 ) ),
				'default'     => array( 'size' => 0, 'unit' => 'px' ),
				'selectors'   => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-input-min-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'letter_spacing',
			array(
				'label'      => __( 'Letter spacing', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => -2, 'max' => 8, 'step' => 0.1 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-letter-spacing: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Colors.                                           */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_colors',
			array(
				'label' => __( 'Colors', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'placeholder_color',
			array(
				'label'     => __( 'Placeholder color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-placeholder: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'bg_color',
			array(
				'label'     => __( 'Background color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'border_color',
			array(
				'label'     => __( 'Border color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'accent_color',
			array(
				'label'       => __( 'Accent color', 'zymarg-algolia' ),
				'description' => __( 'Used for the focus ring, search icon, hover highlight, prices, "See all" link.', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'selectors'   => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-purple: {{VALUE}}; --zymarg-purple-600: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Dropdown.                                         */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_dropdown',
			array(
				'label' => __( 'Results dropdown', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'dropdown_max_height',
			array(
				'label'       => __( 'Max height', 'zymarg-algolia' ),
				'description' => __( 'How tall the dropdown can grow before it scrolls.', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', 'vh' ),
				'range'       => array(
					'px' => array( 'min' => 120, 'max' => 1200, 'step' => 10 ),
					'vh' => array( 'min' => 30,  'max' => 95,   'step' => 1 ),
				),
				'default'     => array( 'size' => 70, 'unit' => 'vh' ),
				'selectors'   => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-dropdown-max-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'dropdown_offset',
			array(
				'label'      => __( 'Offset from bar', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 50, 'step' => 1 ) ),
				'default'    => array( 'size' => 8, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-dropdown-offset: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'dropdown_radius',
			array(
				'label'      => __( 'Border radius', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				'default'    => array( 'size' => 14, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-dropdown-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'dropdown_bg',
			array(
				'label'     => __( 'Background', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-dropdown-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'dropdown_border_color',
			array(
				'label'     => __( 'Border color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-dropdown-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'dropdown_shadow',
			array(
				'label'     => __( 'Drop shadow', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'soft',
				'options'   => array(
					'soft' => __( 'Soft (default)', 'zymarg-algolia' ),
					'hard' => __( 'Hard',           'zymarg-algolia' ),
					'none' => __( 'None',           'zymarg-algolia' ),
				),
				'selectors_dictionary' => array(
					'soft' => '0 10px 40px -10px rgba(123, 63, 228, 0.25), 0 4px 12px rgba(0,0,0,0.05)',
					'hard' => '0 12px 32px rgba(20, 12, 60, 0.18)',
					'none' => 'none',
				),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-dropdown-shadow: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Clear button (X).                                 */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_clear',
			array(
				'label' => __( 'Clear button (×)', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'show_clear_btn',
			array(
				'label'        => __( 'Show clear button', 'zymarg-algolia' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'description'  => __( 'When OFF the X button never appears — typed text can only be cleared by manual selection / backspace.', 'zymarg-algolia' ),
				'label_on'     => __( 'On', 'zymarg-algolia' ),
				'label_off'    => __( 'Off', 'zymarg-algolia' ),
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'clear_position',
			array(
				'label'     => __( 'Position', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'right' => array(
						'title' => __( 'Right', 'zymarg-algolia' ),
						'icon'  => 'eicon-text-align-right',
					),
					'left'  => array(
						'title' => __( 'Left', 'zymarg-algolia' ),
						'icon'  => 'eicon-text-align-left',
					),
				),
				'default'   => 'right',
				'condition' => array( 'show_clear_btn' => 'yes' ),
				'toggle'    => false,
			)
		);

		$this->add_responsive_control(
			'clear_size',
			array(
				'label'      => __( 'Button size', 'zymarg-algolia' ),
				'description'=> __( 'Width and height of the round button. Drag low for a small button, high for a big one.', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 70, 'step' => 1 ) ),
				'default'    => array( 'size' => 26, 'unit' => 'px' ),
				'condition'  => array( 'show_clear_btn' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'clear_icon_size',
			array(
				'label'      => __( 'Icon size (X inside)', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 6, 'max' => 40, 'step' => 1 ) ),
				'default'    => array( 'size' => 14, 'unit' => 'px' ),
				'condition'  => array( 'show_clear_btn' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'clear_radius',
			array(
				'label'      => __( 'Border radius', 'zymarg-algolia' ),
				'description'=> __( 'Drag to 0 for a perfect square, drag high for a circle.', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
					'%'  => array( 'min' => 0, 'max' => 50, 'step' => 1 ),
				),
				'default'    => array( 'size' => 50, 'unit' => '%' ),
				'condition'  => array( 'show_clear_btn' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'clear_gap',
			array(
				'label'      => __( 'Space between X and input text', 'zymarg-algolia' ),
				'description'=> __( 'Margin between the X button and the typed text inside the input.', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				'default'    => array( 'size' => 0, 'unit' => 'px' ),
				'condition'  => array( 'show_clear_btn' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'clear_edge',
			array(
				'label'      => __( 'Distance from edge', 'zymarg-algolia' ),
				'description'=> __( 'Extra margin between the X and the right (or left) edge of the bar.', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				'default'    => array( 'size' => 0, 'unit' => 'px' ),
				'condition'  => array( 'show_clear_btn' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-edge: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'clear_bg',
			array(
				'label'     => __( 'Button background', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_clear_btn' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'clear_color',
			array(
				'label'     => __( 'Icon color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_clear_btn' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'clear_bg_hover',
			array(
				'label'     => __( 'Button background (hover)', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_clear_btn' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-bg-hover: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'clear_color_hover',
			array(
				'label'     => __( 'Icon color (hover)', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_clear_btn' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-clear-color-hover: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Empty state.                                      */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_empty',
			array(
				'label' => __( 'Empty state ("Couldn\'t find...")', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'show_empty_message',
			array(
				'label'        => __( 'Show empty message', 'zymarg-algolia' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'description'  => __( 'When OFF the "Couldn\'t find what you\'re looking for? Request Here" CTA is hidden — when zero results match, the dropdown closes completely instead of showing the CTA.', 'zymarg-algolia' ),
				'label_on'     => __( 'On', 'zymarg-algolia' ),
				'label_off'    => __( 'Off', 'zymarg-algolia' ),
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_responsive_control(
			'empty_text_size',
			array(
				'label'      => __( 'Message text size', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 32, 'step' => 1 ) ),
				'default'    => array( 'size' => 14, 'unit' => 'px' ),
				'condition'  => array( 'show_empty_message' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-text-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'empty_text_color',
			array(
				'label'     => __( 'Message text color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_empty_message' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-text-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'empty_btn_size',
			array(
				'label'      => __( 'Button text size', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 32, 'step' => 1 ) ),
				'default'    => array( 'size' => 14, 'unit' => 'px' ),
				'condition'  => array( 'show_empty_message' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-btn-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'empty_btn_bg',
			array(
				'label'     => __( 'Button background', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_empty_message' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-btn-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_btn_bg_hover',
			array(
				'label'     => __( 'Button background (hover)', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_empty_message' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-btn-bg-h: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_btn_color',
			array(
				'label'     => __( 'Button text color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => array( 'show_empty_message' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-btn-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_btn_radius',
			array(
				'label'      => __( 'Button radius', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				'default'    => array( 'size' => 999, 'unit' => 'px' ),
				'condition'  => array( 'show_empty_message' => 'yes' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-btn-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget on the frontend AND inside the Elementor editor.
	 */
	protected function render() {
		$settings    = $this->get_settings_for_display();
		$placeholder = isset( $settings['placeholder'] ) ? trim( (string) $settings['placeholder'] ) : '';
		$stretch     = ! empty( $settings['stretch'] ) && 'yes' === $settings['stretch'];
		// Default = ON. So treat anything other than explicit empty string as "show".
		$show_dd     = ! isset( $settings['show_dropdown'] ) || 'yes' === $settings['show_dropdown'];
		$no_dropdown = ! $show_dd;
		$show_empty  = ! isset( $settings['show_empty_message'] ) || 'yes' === $settings['show_empty_message'];
		$no_empty    = ! $show_empty;
		$show_clear  = ! isset( $settings['show_clear_btn'] ) || 'yes' === $settings['show_clear_btn'];
		$no_clear    = ! $show_clear;
		$clear_left  = ! empty( $settings['clear_position'] ) && 'left' === $settings['clear_position'];

		// Section toggles (default: products + categories ON, vendors OFF).
		$show_products   = ! isset( $settings['show_products_section'] )   || 'yes' === $settings['show_products_section'];
		$show_categories = ! isset( $settings['show_categories_section'] ) || 'yes' === $settings['show_categories_section'];
		$show_vendors    = isset( $settings['show_vendors_section'] ) && 'yes' === $settings['show_vendors_section'];

		if ( '' !== $placeholder ) {
			add_filter(
				'zymarg_algolia_placeholder',
				function () use ( $placeholder ) {
					return $placeholder;
				}
			);
		}

		echo Zymarg_Algolia_Frontend::render_html( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'stretch'        => $stretch,
			'noDropdown'     => $no_dropdown,
			'noEmpty'        => $no_empty,
			'noClear'        => $no_clear,
			'clearLeft'      => $clear_left,
			'showProducts'   => $show_products,
			'showCategories' => $show_categories,
			'showVendors'    => $show_vendors,
		) );
	}
}
