<?php
/**
 * Elementor widget: "ZYMARG Search".
 *
 * Adds a draggable widget to the Elementor panel under the "ZYMARG" category.
 * Renders the same instant-search bar as the shortcode/block. The widget
 * renders LIVE in the Elementor editor preview iframe — no need to publish
 * to see the search bar appear / move when you change settings on the
 * Advanced tab.
 *
 * Controls:
 *   - Placeholder text
 *   - Alignment (left / center / right) — responsive
 *   - Max width — responsive
 *   - Accent / border / background color
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
	 * Tell Elementor we depend on the frontend search script & style so they
	 * are enqueued in the editor preview iframe whenever the widget appears.
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

		/* ---------- Content tab ---------- */

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
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min'  => 200,
						'max'  => 1200,
						'step' => 10,
					),
					'%'  => array(
						'min'  => 30,
						'max'  => 100,
						'step' => 1,
					),
				),
				'default'    => array(
					'size' => 720,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ---------- Style tab ---------- */

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Style', 'zymarg-algolia' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'accent_color',
			array(
				'label'     => __( 'Accent color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-purple: {{VALUE}}; --zymarg-purple-600: {{VALUE}};',
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
			'text_color',
			array(
				'label'     => __( 'Text color', 'zymarg-algolia' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-text: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'input_height',
			array(
				'label'      => __( 'Input height', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 36,
						'max'  => 80,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-inputwrap' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => __( 'Border radius', 'zymarg-algolia' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 40,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-algolia-wrapper' => '--zymarg-radius: {{SIZE}}{{UNIT}};',
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
