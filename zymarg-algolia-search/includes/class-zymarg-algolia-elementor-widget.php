<?php
/**
 * Elementor widget: "ZYMARG Search".
 *
 * Adds a draggable widget to the Elementor panel under the "ZYMARG"
 * category. Renders the same instant-search bar as the shortcode/block.
 *
 * v1.0.6 — every dimension and color is now a CSS variable, so this
 * widget exposes a comprehensive set of Elementor controls covering:
 *
 *   Content tab:
 *     - Placeholder text
 *     - Alignment (responsive)
 *     - Max width (responsive)
 *
 *   Style tab > Input:
 *     - Input height, padding, font size / weight / letter-spacing
 *     - Border color, width, radius
 *     - Background color
 *     - Text + placeholder colors
 *     - Accent / focus ring color
 *     - Icon size + gap
 *
 *   Style tab > Dropdown:
 *     - Max height (so the dropdown can shrink/grow)
 *     - Background, border, radius, shadow toggle
 *     - Offset (gap between input and dropdown)
 *
 *   Style tab > Empty state:
 *     - Text color, button bg / hover / text colors, button radius
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Class Zymarg_Algolia_Elementor_Widget
 */
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

	/**
	 * Enqueue our frontend script + style in the editor preview iframe so
	 * the widget renders live (no need to publish to see it).
	 */
	public function get_script_depends() {
		return array( Zymarg_Algolia_Frontend::SCRIPT_HANDLE );
	}

	public function get_style_depends() {
		return array( Zymarg_Algolia_Frontend::STYLE_HANDLE );
	}

	/**
	 * Register Elementor controls.
	 */
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
					'flex-start' => array(
						'title' => __( 'Left', 'zymarg-algolia' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'zymarg-algolia' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'zymarg-algolia' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}}' => 'display:flex; justify-content: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'max_width',
			array(
				'label'      => __( 'Max width', 'zymarg-algolia' ),
				'description' => __( 'Set this to "100%" to fill the entire container.', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array(
					'px' => array( 'min' => 200, 'max' => 1600, 'step' => 10 ),
					'%'  => array( 'min' => 30,  'max' => 100,  'step' => 1 ),
					'vw' => array( 'min' => 30,  'max' => 100,  'step' => 1 ),
				),
				'default'    => array( 'size' => 720, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Input.                                            */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_input',
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
				'range'      => array(
					'px' => array( 'min' => 32, 'max' => 100, 'step' => 1 ),
				),
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
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
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
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 60, 'step' => 1 ),
				),
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
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 6, 'step' => 0.5 ),
				),
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
				'range'      => array(
					'px' => array( 'min' => 12, 'max' => 32, 'step' => 1 ),
				),
				'default'    => array( 'size' => 18, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'icon_gap',
			array(
				'label'      => __( 'Icon gap', 'zymarg-algolia' ),
				'description' => __( 'Space between the icon and the input text.', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 30, 'step' => 1 ),
				),
				'default'    => array( 'size' => 10, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-icon-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ============================================================ */
		/* Style tab > Typography.                                       */
		/* ============================================================ */

		$this->start_controls_section(
			'section_style_typography',
			array(
				'label' => __( 'Typography', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'font_size',
			array(
				'label'      => __( 'Text size', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 11, 'max' => 28, 'step' => 1 ),
				),
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
					'300' => __( 'Light (300)', 'zymarg-algolia' ),
					'400' => __( 'Normal (400)', 'zymarg-algolia' ),
					'500' => __( 'Medium (500)', 'zymarg-algolia' ),
					'600' => __( 'Semi-bold (600)', 'zymarg-algolia' ),
					'700' => __( 'Bold (700)', 'zymarg-algolia' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-font-weight: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'letter_spacing',
			array(
				'label'      => __( 'Letter spacing', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => -2, 'max' => 8, 'step' => 0.1 ),
				),
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
				'label'      => __( 'Max height', 'zymarg-algolia' ),
				'description' => __( 'How tall the dropdown can grow before it scrolls. Use vh for screen-height percent.', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array(
					'px' => array( 'min' => 120, 'max' => 900, 'step' => 10 ),
					'vh' => array( 'min' => 30,  'max' => 95,  'step' => 1 ),
				),
				'default'    => array( 'size' => 70, 'unit' => 'vh' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-dropdown-max-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'dropdown_offset',
			array(
				'label'       => __( 'Offset from bar', 'zymarg-algolia' ),
				'description' => __( 'Vertical gap between the search bar and the dropdown.', 'zymarg-algolia' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array(
					'px' => array( 'min' => 0, 'max' => 30, 'step' => 1 ),
				),
				'default'     => array( 'size' => 8, 'unit' => 'px' ),
				'selectors'   => array(
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
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
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
					'hard' => __( 'Hard', 'zymarg-algolia' ),
					'none' => __( 'None', 'zymarg-algolia' ),
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
			'empty_text_color',
			array(
				'label'     => __( 'Text color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-empty-text-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_btn_bg',
			array(
				'label'     => __( 'Button background', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
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
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'default'    => array( 'size' => 999, 'unit' => 'px' ),
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

		if ( '' !== $placeholder ) {
			add_filter(
				'zymarg_algolia_placeholder',
				function () use ( $placeholder ) {
					return $placeholder;
				}
			);
		}

		echo Zymarg_Algolia_Frontend::render_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
