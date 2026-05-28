<?php
/**
 * "ZYMARG Search" Gutenberg block + classic widget + Elementor widget loader.
 *
 * Adds a real, dragable widget to:
 *   - The WordPress block inserter (Gutenberg / Site Editor / posts / pages).
 *   - Legacy widget areas (Appearance -> Widgets, Astra header widget zones).
 *   - The Elementor panel (under a "ZYMARG" category) — when Elementor is active.
 *
 * No more typing or remembering [zymarg_algolia_search]. The shortcode still
 * works for backwards compatibility but is no longer the recommended path.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Block
 */
class Zymarg_Algolia_Block {

	const BLOCK_NAME = 'zymarg/algolia-search';

	public function __construct() {
		// Gutenberg block (registered on init, after frontend assets register).
		add_action( 'init', array( $this, 'register_block' ), 20 );

		// Classic widget (Appearance -> Widgets, Astra header widget zones, etc).
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// Make the search bar's CSS+JS available in the Gutenberg editor preview iframe.
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

	/**
	 * Register the Gutenberg block.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Block editor JS — registers "ZYMARG Search" in the inserter.
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
					'placeholder' => array(
						'type'    => 'string',
						'default' => '',
					),
					'align'       => array(
						'type' => 'string',
					),
				),
				'supports'        => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
			)
		);
	}

	/**
	 * Server-side render for the Gutenberg block. Used both on the
	 * frontend and inside the editor's ServerSideRender preview.
	 *
	 * @param array $attrs Block attributes.
	 * @return string
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

		$align       = isset( $attrs['align'] ) ? sanitize_html_class( $attrs['align'] ) : '';
		$align_class = $align ? ' align' . $align : '';

		return '<div class="zymarg-algolia-block-wrap' . esc_attr( $align_class ) . '">' .
			Zymarg_Algolia_Frontend::render_html() .
			'</div>';
	}

	/**
	 * Enqueue the live search assets inside the Gutenberg / Elementor editor.
	 * Without this the live preview shows the markup but the input is dead
	 * (no instant dropdown). We still skip enqueuing if Algolia is not yet
	 * configured (avoids console errors).
	 */
	public function enqueue_in_editor() {
		$app_id     = zymarg_algolia_get_setting( 'app_id' );
		$search_key = zymarg_algolia_get_setting( 'search_api_key' );
		if ( empty( $app_id ) || empty( $search_key ) ) {
			return;
		}
		// Style + script handles are registered by Zymarg_Algolia_Frontend on init.
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

	/**
	 * Add a "ZYMARG" category to the Elementor panel.
	 *
	 * @param mixed $manager Elementor categories manager.
	 */
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

	/**
	 * Register the Elementor widget.
	 *
	 * @param mixed $widgets_manager Elementor widgets manager.
	 */
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
 * Classic WP_Widget for legacy widget areas (sidebars, Astra header widget
 * blocks, footer columns, etc).
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
		echo Zymarg_Algolia_Frontend::render_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$title       = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		$placeholder = isset( $instance['placeholder'] ) ? (string) $instance['placeholder'] : '';
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
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'       => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'placeholder' => isset( $new_instance['placeholder'] ) ? sanitize_text_field( $new_instance['placeholder'] ) : '',
		);
	}
}
