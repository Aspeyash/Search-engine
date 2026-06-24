<?php
/**
 * Shortcode + Elementor-friendly hooks for placing the search bar.
 *
 * Usage:
 *   [zymarg_algolia_search]
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Shortcode
 */
class Zymarg_Algolia_Shortcode {

	public function __construct() {
		add_shortcode( 'zymarg_algolia_search', array( $this, 'render' ) );

		// Allow shortcodes inside Elementor text widgets / HTML widgets / shortcode widget without breaking.
		add_filter( 'widget_text', 'do_shortcode' );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'placeholder' => '',
				'icon_only'   => '',
			),
			$atts,
			'zymarg_algolia_search'
		);

		// If the user passed a placeholder, override the JS one.
		if ( ! empty( $atts['placeholder'] ) ) {
			add_filter(
				'zymarg_algolia_placeholder',
				function ( $default ) use ( $atts ) {
					return $atts['placeholder'];
				}
			);
		}

		$icon_only = in_array( strtolower( (string) $atts['icon_only'] ), array( '1', 'yes', 'true', 'on' ), true );

		return Zymarg_Algolia_Frontend::render_html( array(
			'iconOnly' => $icon_only,
		) );
	}
}
