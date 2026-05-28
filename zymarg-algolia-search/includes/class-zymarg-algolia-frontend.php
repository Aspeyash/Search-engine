<?php
/**
 * Frontend: register/enqueue search assets + render search bar HTML.
 *
 * v1.0.6+: NO external library. The search script talks to Algolia's REST
 * API directly via window.fetch(). v1.0.7: localize now ships the plugin
 * version + a `stretch` flag to support unlimited bar width.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Frontend
 */
class Zymarg_Algolia_Frontend {

	const SCRIPT_HANDLE = 'zymarg-algolia-search';
	const STYLE_HANDLE  = 'zymarg-algolia-search';

	public function __construct() {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	protected function should_load() {
		return (bool) apply_filters( 'zymarg_algolia_should_enqueue', true );
	}

	/**
	 * Register the search script + style.
	 */
	public function register_assets() {
		if ( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			ZYMARG_ALGOLIA_URL . 'assets/js/zymarg-search.js',
			array(),
			ZYMARG_ALGOLIA_VERSION,
			true
		);

		wp_register_style(
			self::STYLE_HANDLE,
			ZYMARG_ALGOLIA_URL . 'assets/css/zymarg-search.css',
			array(),
			ZYMARG_ALGOLIA_VERSION
		);

		$app_id     = zymarg_algolia_get_setting( 'app_id' );
		$search_key = zymarg_algolia_get_setting( 'search_api_key' );

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'ZymargAlgolia',
			array(
				'version'       => ZYMARG_ALGOLIA_VERSION,
				'appId'         => $app_id,
				'searchKey'     => $search_key,
				'indexProducts' => zymarg_algolia_index_name( 'products' ),
				'indexVendors'  => zymarg_algolia_index_name( 'vendors' ),
				'indexCats'     => zymarg_algolia_index_name( 'categories' ),
				'communityUrl'  => zymarg_algolia_get_setting( 'community_url' ),
				'noResultsText' => zymarg_algolia_get_setting( 'no_results_text' ),
				'requestBtn'    => zymarg_algolia_get_setting( 'request_btn' ),
				'placeholder'   => __( 'Search products, vendors, categories…', 'zymarg-algolia' ),
				'i18n'          => array(
					'products'   => __( 'Products', 'zymarg-algolia' ),
					'vendors'    => __( 'Vendors', 'zymarg-algolia' ),
					'categories' => __( 'Categories', 'zymarg-algolia' ),
					'by'         => __( 'by', 'zymarg-algolia' ),
					'viewAll'    => __( 'See all results', 'zymarg-algolia' ),
				),
				'currencySym'   => function_exists( 'get_woocommerce_currency_symbol' )
					? html_entity_decode( get_woocommerce_currency_symbol() )
					: '$',
			)
		);
	}

	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}
		$this->register_assets();

		$app_id     = zymarg_algolia_get_setting( 'app_id' );
		$search_key = zymarg_algolia_get_setting( 'search_api_key' );

		if ( empty( $app_id ) || empty( $search_key ) ) {
			return;
		}

		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Render the search bar HTML. Used by shortcode, Gutenberg block,
	 * classic widget and the Elementor widget.
	 *
	 * @param array $args Optional. Supported keys:
	 *   - 'stretch'    (bool) — drop max-width so the bar fills its parent.
	 *   - 'fullBleed'  (bool) — break out of parent + span the entire viewport.
	 *   - 'noDropdown' (bool) — hide the live results dropdown entirely.
	 * @return string
	 */
	public static function render_html( $args = array() ) {
		$args        = is_array( $args ) ? $args : array();
		$stretch     = ! empty( $args['stretch'] );
		$full_bleed  = ! empty( $args['fullBleed'] );
		$no_dropdown = ! empty( $args['noDropdown'] );

		$classes = array( 'zymarg-algolia-wrapper' );
		if ( $stretch )     $classes[] = 'zymarg-stretch';
		if ( $full_bleed )  $classes[] = 'zymarg-fullbleed';
		if ( $no_dropdown ) $classes[] = 'zymarg-no-dropdown';
		$wrap_cls = implode( ' ', $classes );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrap_cls ); ?>" data-zymarg-search>
			<div class="zymarg-algolia-orb zymarg-algolia-orb-1" aria-hidden="true"></div>
			<div class="zymarg-algolia-orb zymarg-algolia-orb-2" aria-hidden="true"></div>

			<form role="search" class="zymarg-algolia-form"
				action="<?php echo esc_url( home_url( '/' ) ); ?>"
				method="get">
				<div class="zymarg-algolia-inputwrap">
					<svg class="zymarg-algolia-icon" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"
							fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<input type="search"
						name="s"
						class="zymarg-algolia-input"
						autocomplete="off"
						spellcheck="false"
						aria-label="<?php esc_attr_e( 'Search', 'zymarg-algolia' ); ?>"
						placeholder="<?php echo esc_attr( apply_filters( 'zymarg_algolia_placeholder', __( 'Search products, vendors, categories…', 'zymarg-algolia' ) ) ); ?>" />
					<button type="button" class="zymarg-algolia-clear" aria-label="Clear" hidden>
						<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
							<path d="M6 6l12 12M18 6L6 18" stroke="currentColor"
								stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
			</form>

			<div class="zymarg-algolia-dropdown" role="listbox" hidden>
				<div class="zymarg-algolia-loading" hidden>
					<span class="zymarg-algolia-spinner"></span>
				</div>
				<div class="zymarg-algolia-results"></div>
				<div class="zymarg-algolia-empty" hidden>
					<p class="zymarg-algolia-empty-text"></p>
					<a class="zymarg-algolia-empty-btn" href="#"></a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
