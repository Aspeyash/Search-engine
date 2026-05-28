<?php
/**
 * "ZYMARG Search" Gutenberg block + classic widget + Elementor widget loader.
 *
 * v1.0.7 changes:
 *   - New `stretch` block attribute (toggle in sidebar) — drops max-width
 *     so the bar fills its container.
 *   - New attributes for input internals: inputPaddingY, lineHeight,
 *     inputMinWidth.
 *   - Max-width now accepts up to 3000 px.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zymarg_Algolia_Block {

	const BLOCK_NAME = 'zymarg/algolia-search';

	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ), 20 );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_in_editor' ) );

		// Elementor integration.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_in_editor' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_in_editor' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_in_editor' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Gutenberg block.                                                    */
	/* ------------------------------------------------------------------ */

	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'zymarg-algolia-block',
			ZYMARG_ALGOLIA_URL . 'assets/js/zymarg-block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-server-side-render',
				'wp-i18n',
			),
			ZYMARG_ALGOLIA_VERSION,
			true
		);

		register_block_type(
			self::BLOCK_NAME,
			array(
				'editor_script'   => 'zymarg-algolia-block',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'placeholder'        => array( 'type' => 'string', 'default' => '' ),
					'align'              => array( 'type' => 'string' ),

					// Layout.
					'stretch'            => array( 'type' => 'boolean', 'default' => false ),
					'maxWidth'           => array( 'type' => 'number' ),
					'inputHeight'        => array( 'type' => 'number' ),
					'fontSize'           => array( 'type' => 'number' ),
					'borderRadius'       => array( 'type' => 'number' ),
					'paddingX'           => array( 'type' => 'number' ),
					'iconSize'           => array( 'type' => 'number' ),

					// Input field internals.
					'inputPaddingY'      => array( 'type' => 'number' ),
					'lineHeight'         => array( 'type' => 'number' ),
					'inputMinWidth'      => array( 'type' => 'number' ),

					// Dropdown.
					'dropdownMaxHeight'  => array( 'type' => 'number' ),
					'dropdownRadius'     => array( 'type' => 'number' ),
					'dropdownOffset'     => array( 'type' => 'number' ),

					// Colors.
					'textColor'          => array( 'type' => 'string' ),
					'placeholderColor'   => array( 'type' => 'string' ),
					'bgColor'            => array( 'type' => 'string' ),
					'borderColor'        => array( 'type' => 'string' ),
					'accentColor'        => array( 'type' => 'string' ),
					'dropdownBg'         => array( 'type' => 'string' ),
				),
				'supports'        => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
			)
		);
	}

	/**
	 * Server-side render for the Gutenberg block.
	 */
	public function render_block( $attrs ) {
		$attrs = is_array( $attrs ) ? $attrs : array();

		if ( ! empty( $attrs['placeholder'] ) ) {
			$ph = (string) $attrs['placeholder'];
			add_filter(
				'zymarg_algolia_placeholder',
				function () use ( $ph ) {
					return $ph;
				}
			);
		}

		$stretch = ! empty( $attrs['stretch'] );

		// Build inline `style="..."` of CSS variables (cascade to inner wrapper).
		$vars = array();

		$num_map = array(
			'maxWidth'          => '--zymarg-max-width',
			'inputHeight'       => '--zymarg-input-height',
			'fontSize'          => '--zymarg-font-size',
			'borderRadius'      => '--zymarg-radius',
			'paddingX'          => '--zymarg-padding-x',
			'iconSize'          => '--zymarg-icon-size',
			'inputPaddingY'     => '--zymarg-input-padding-y',
			'inputMinWidth'     => '--zymarg-input-min-width',
			'dropdownMaxHeight' => '--zymarg-dropdown-max-height',
			'dropdownRadius'    => '--zymarg-dropdown-radius',
			'dropdownOffset'    => '--zymarg-dropdown-offset',
		);
		foreach ( $num_map as $key => $css_var ) {
			if ( isset( $attrs[ $key ] ) && is_numeric( $attrs[ $key ] ) ) {
				$vars[ $css_var ] = intval( $attrs[ $key ] ) . 'px';
			}
		}

		// lineHeight is unitless.
		if ( isset( $attrs['lineHeight'] ) && is_numeric( $attrs['lineHeight'] ) ) {
			$vars['--zymarg-input-line-height'] = (string) floatval( $attrs['lineHeight'] );
		}

		$color_map = array(
			'textColor'        => '--zymarg-text',
			'placeholderColor' => '--zymarg-placeholder',
			'bgColor'          => '--zymarg-bg',
			'borderColor'      => '--zymarg-border',
			'dropdownBg'       => '--zymarg-dropdown-bg',
		);
		foreach ( $color_map as $key => $css_var ) {
			if ( ! empty( $attrs[ $key ] ) ) {
				$color = $this->sanitize_color( $attrs[ $key ] );
				if ( $color ) {
					$vars[ $css_var ] = $color;
				}
			}
		}

		if ( ! empty( $attrs['accentColor'] ) ) {
			$accent = $this->sanitize_color( $attrs['accentColor'] );
			if ( $accent ) {
				$vars['--zymarg-purple']     = $accent;
				$vars['--zymarg-purple-600'] = $accent;
			}
		}

		$style_parts = array();
		foreach ( $vars as $k => $v ) {
			$style_parts[] = $k . ':' . $v;
		}
		$style_attr = $style_parts
			? ' style="' . esc_attr( implode( ';', $style_parts ) ) . '"'
			: '';

		$align       = isset( $attrs['align'] ) ? sanitize_html_class( $attrs['align'] ) : '';
		$align_class = $align ? ' align' . $align : '';

		return '<div class="zymarg-algolia-block-wrap' . esc_attr( $align_class ) . '"' . $style_attr . '>' .
			Zymarg_Algolia_Frontend::render_html( array( 'stretch' => $stretch ) ) .
			'</div>';
	}

	/**
	 * Light-weight color sanitization. Allows hex, rgb(a), hsl(a), and CSS
	 * keywords. Rejects anything that could break out of the style attribute.
	 */
	protected function sanitize_color( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/[<>"\'`]/', $value ) ) {
			return '';
		}
		if ( preg_match( '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(rgb|rgba|hsl|hsla)\([0-9.,%\s\/-]+\)$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^[a-zA-Z]+$/', $value ) ) {
			return $value;
		}
		return '';
	}

	public function enqueue_in_editor() {
		$app_id     = zymarg_algolia_get_setting( 'app_id' );
		$search_key = zymarg_algolia_get_setting( 'search_api_key' );
		if ( empty( $app_id ) || empty( $search_key ) ) {
			return;
		}
		wp_enqueue_style( Zymarg_Algolia_Frontend::STYLE_HANDLE );
		wp_enqueue_script( Zymarg_Algolia_Frontend::SCRIPT_HANDLE );
	}

	/* ------------------------------------------------------------------ */
	/* Classic widget.                                                     */
	/* ------------------------------------------------------------------ */

	public function register_widget() {
		register_widget( 'Zymarg_Algolia_Classic_Widget' );
	}

	/* ------------------------------------------------------------------ */
	/* Elementor.                                                          */
	/* ------------------------------------------------------------------ */

	public function register_elementor_category( $manager ) {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'add_category' ) ) {
			return;
		}
		$manager->add_category(
			'zymarg',
			array(
				'title' => __( 'ZYMARG', 'zymarg-algolia' ),
				'icon'  => 'eicon-search',
			)
		);
	}

	public function register_elementor_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-elementor-widget.php';
		if ( class_exists( 'Zymarg_Algolia_Elementor_Widget' ) && is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( new Zymarg_Algolia_Elementor_Widget() );
		}
	}
}

/**
 * Classic WP_Widget for legacy widget areas.
 */
class Zymarg_Algolia_Classic_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'zymarg_algolia_search_widget',
			__( 'ZYMARG Search', 'zymarg-algolia' ),
			array(
				'description' => __( 'Algolia-powered instant search bar (products, vendors, categories).', 'zymarg-algolia' ),
				'classname'   => 'zymarg-algolia-classic-widget',
			)
		);
	}

	public function widget( $args, $instance ) {
		$title       = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) : '';
		$placeholder = ! empty( $instance['placeholder'] ) ? $instance['placeholder'] : '';
		$stretch     = ! empty( $instance['stretch'] );

		if ( $placeholder ) {
			$ph = (string) $placeholder;
			add_filter(
				'zymarg_algolia_placeholder',
				function () use ( $ph ) {
					return $ph;
				}
			);
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo Zymarg_Algolia_Frontend::render_html( array( 'stretch' => $stretch ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$title       = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		$placeholder = isset( $instance['placeholder'] ) ? (string) $instance['placeholder'] : '';
		$stretch     = ! empty( $instance['stretch'] );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'zymarg-algolia' ); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>">
				<?php esc_html_e( 'Placeholder text:', 'zymarg-algolia' ); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'placeholder' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $placeholder ); ?>"
				placeholder="<?php esc_attr_e( 'Search products, vendors, categories…', 'zymarg-algolia' ); ?>" />
		</p>
		<p>
			<input
				id="<?php echo esc_attr( $this->get_field_id( 'stretch' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'stretch' ) ); ?>"
				type="checkbox"
				value="1"
				<?php checked( $stretch, true ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'stretch' ) ); ?>">
				<?php esc_html_e( 'Stretch to full container width', 'zymarg-algolia' ); ?>
			</label>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'       => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'placeholder' => isset( $new_instance['placeholder'] ) ? sanitize_text_field( $new_instance['placeholder'] ) : '',
			'stretch'     => ! empty( $new_instance['stretch'] ) ? 1 : 0,
		);
	}
}
