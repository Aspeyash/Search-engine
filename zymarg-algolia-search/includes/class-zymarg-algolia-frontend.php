<?php
/**
 * Frontend: register/enqueue search assets + render search bar HTML.
 *
 * v1.0.36: NO external library. The search script talks to Algolia's REST
 * API directly via window.fetch(). v1.0.7: localize now ships the plugin
 * version + a `stretch` flag to support unlimited bar width.
 *
 * v1.0.36 changes:
 *   - zymarg-search.js now loads with strategy=>'defer' on WP 6.3+,
 *     falling back to plain in-footer on WP 6.2. Safe because the script
 *     already guards its own init with:
 *       document.readyState !== 'loading'
 *       ? fn() : document.addEventListener('DOMContentLoaded', fn)
 *     It also retries config detection via setTimeout, so defer cannot
 *     cause a race with wp_localize_script's inline <script> tag
 *     (which is output before the deferred <script src> tag executes).
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
			self::defer_args()   // defer on WP 6.3+, in-footer fallback on 6.2
		);

		wp_register_style(
			self::STYLE_HANDLE,
			ZYMARG_ALGOLIA_URL . 'assets/css/zymarg-search.css',
			array(),
			ZYMARG_ALGOLIA_VERSION
		);

		$app_id     = zymarg_algolia_get_setting( 'app_id' );
		$search_key = zymarg_algolia_get_setting( 'search_api_key' );

		// Pull trending searches from the dashboard's analytics cache (1.0.15+).
		// Read-only, never blocks the page — falls back to admin-defined default list.
		$trending = array();
		if ( class_exists( 'Zymarg_Algolia_Dashboard' ) && method_exists( 'Zymarg_Algolia_Dashboard', 'get_cached_trending_searches' ) ) {
			$trending = Zymarg_Algolia_Dashboard::get_cached_trending_searches( 6 );
		}
		// Fallback: use admin-defined trending list when analytics cache is empty (1.0.36).
		// Admins set this via Settings → ZYMARG Search Engine → Trending Searches.
		// If that field is also empty, show a generic marketplace-appropriate default set.
		if ( empty( $trending ) ) {
			$raw = zymarg_algolia_get_setting( 'trending_fallback', '' );
			if ( ! empty( $raw ) ) {
				$trending = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
			}
		}
		if ( empty( $trending ) ) {
			$trending = array( 'iPhone', 'Wireless Earbuds', 'Gaming Chair', 'Smartwatch', 'Laptop', 'Mechanical Keyboard' );
		}
		$trending = array_slice( $trending, 0, 6 );

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'ZymargAlgolia',
			array(
				'version'         => ZYMARG_ALGOLIA_VERSION,
				'appId'           => $app_id,
				'searchKey'       => $search_key,
				'indexProducts'   => zymarg_algolia_index_name( 'products' ),
				'indexVendors'    => zymarg_algolia_index_name( 'vendors' ),
				'indexCats'       => zymarg_algolia_index_name( 'categories' ),
				'communityUrl'    => zymarg_algolia_get_setting( 'community_url' ),
				'noResultsText'   => zymarg_algolia_get_setting( 'no_results_text' ),
				'requestBtn'      => zymarg_algolia_get_setting( 'request_btn' ),
				'placeholder'     => __( 'Search products, vendors, categories…', 'zymarg-algolia' ),
				'trendingSearches' => $trending,
				'showTrending'    => (int) zymarg_algolia_get_setting( 'show_trending', 1 ),
				// Smart-feature flags (2.0.0). 1 = on, 0 = off. The JS treats a
				// missing flag as ON, so older cached config never disables anything.
				'features'        => array(
					'recent'      => (int) zymarg_algolia_get_setting( 'feat_recent', 1 ),
					'keyboard'    => (int) zymarg_algolia_get_setting( 'feat_keyboard', 1 ),
					'insights'    => (int) zymarg_algolia_get_setting( 'feat_insights', 1 ),
					'related'     => (int) zymarg_algolia_get_setting( 'feat_related', 1 ),
					'resultCount' => (int) zymarg_algolia_get_setting( 'feat_result_count', 1 ),
				),
				'i18n'            => array(
					'products'         => __( 'Products', 'zymarg-algolia' ),
					'vendors'          => __( 'Vendors', 'zymarg-algolia' ),
					'categories'       => __( 'Categories', 'zymarg-algolia' ),
					'by'               => __( 'by', 'zymarg-algolia' ),
					'viewAll'          => __( 'See all results', 'zymarg-algolia' ),
					'recentSearches'   => __( 'Recent searches', 'zymarg-algolia' ),
					'trendingSearches' => __( 'Trending searches', 'zymarg-algolia' ),
					'clear'            => __( 'Clear', 'zymarg-algolia' ),
					'resultSingular'   => __( 'result', 'zymarg-algolia' ),
					'resultPlural'     => __( 'results', 'zymarg-algolia' ),
					'relatedFor'       => __( 'Showing related results for', 'zymarg-algolia' ),
				),
				'currencySym'     => function_exists( 'get_woocommerce_currency_symbol' )
					? html_entity_decode( get_woocommerce_currency_symbol() )
					: '$',
				// Cross-device sync (1.0.36) — only populated for logged-in users.
				'syncEnabled'  => is_user_logged_in() ? 1 : 0,
				'syncAjaxUrl'  => is_user_logged_in() ? admin_url( 'admin-ajax.php' ) : '',
				'syncNonce'    => is_user_logged_in() ? wp_create_nonce( Zymarg_Algolia_Search_Sync::NONCE ) : '',
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
	 * Return the correct 5th-argument value for wp_register_script() to
	 * load a script in the footer with defer strategy.
	 *
	 * WordPress 6.3+ supports:
	 *   array( 'in_footer' => true, 'strategy' => 'defer' )
	 *
	 * WordPress 6.2 / 6.0 (our minimum) only accepts a boolean.
	 * We detect support at runtime so the plugin stays compatible with both.
	 *
	 * Why defer is safe for zymarg-search.js:
	 *   The script already handles delayed execution itself (lines 276-286):
	 *     document.readyState !== 'loading' ? fn() : addEventListener(...)
	 *   The wp_localize_script inline <script> block is output BEFORE the
	 *   deferred <script src> tag, so window.ZymargAlgolia is available the
	 *   moment the deferred script runs. The script also retries config
	 *   detection with setTimeout as an extra safety net.
	 *
	 * @return array|true
	 */
	public static function defer_args() {
		static $supports_strategy = null;
		if ( null === $supports_strategy ) {
			$supports_strategy = version_compare( get_bloginfo( 'version' ), '6.3', '>=' );
		}
		if ( $supports_strategy ) {
			return array(
				'in_footer' => true,
				'strategy'  => 'defer',
			);
		}
		return true; // Fallback: in-footer only, no regression on WP 6.2.
	}

	/**
	 * Render the search bar HTML. Used by shortcode, Gutenberg block,
	 * classic widget and the Elementor widget.
	 *
	 * @param array $args Optional. Supported keys:
	 *   - 'stretch'        (bool) — drop max-width so the bar fills its parent.
	 *   - 'noDropdown'     (bool) — hide the live results dropdown entirely.
	 *   - 'noEmpty'        (bool) — hide the "Couldn't find..." empty-state CTA.
	 *   - 'noClear'        (bool) — hide the X (clear) button entirely.
	 *   - 'clearLeft'      (bool) — place the X on the LEFT side of the input.
	 *   - 'showProducts'   (bool, default true)  — render the Products section.
	 *   - 'showCategories' (bool, default true)  — render the Categories section.
	 *   - 'showVendors'    (bool, default false) — render the Vendors section.
	 *   - 'spinnerMode'    (string, default 'searching') — 'searching' | 'focus' | 'hidden'.
	 *   - 'categoryScope'  (bool, default false) — show category scope pills above results (1.0.36).
	 *
	 * Section visibility is passed to the JS via data attributes on the
	 * wrapper. The JS then skips the API call for any hidden section and
	 * renders the rest in the order: Products → Categories → Vendors.
	 *
	 * @return string
	 */
	public static function render_html( $args = array() ) {
		$args        = is_array( $args ) ? $args : array();
		$stretch     = ! empty( $args['stretch'] );
		$no_dropdown = ! empty( $args['noDropdown'] );
		$no_empty    = ! empty( $args['noEmpty'] );
		$no_clear    = ! empty( $args['noClear'] );
		$clear_left  = ! empty( $args['clearLeft'] );

		// Honor the global "CTA Mode" setting (1.0.12+). When the user has
		// chosen to show the CTA on the search results page (or hidden it
		// everywhere), the dropdown empty-state CTA must be suppressed even
		// if the per-widget toggle is ON — otherwise the user would see two
		// CTAs at once.
		$global_cta_mode = zymarg_algolia_get_setting( 'cta_mode', 'dropdown' );
		if ( 'dropdown' !== $global_cta_mode ) {
			$no_empty = true;
		}

		// Section visibility — defaults: products + categories ON, vendors OFF.
		$show_products   = ! array_key_exists( 'showProducts', $args )   || ! empty( $args['showProducts'] );
		$show_categories = ! array_key_exists( 'showCategories', $args ) || ! empty( $args['showCategories'] );
		$show_vendors    = ! empty( $args['showVendors'] );

		// Category scope pills (1.0.36) — default OFF.
		$category_scope = ! empty( $args['categoryScope'] );

		// 1.0.17: loading-spinner mode (per-instance).
		$spinner_mode = isset( $args['spinnerMode'] ) ? (string) $args['spinnerMode'] : 'searching';
		if ( ! in_array( $spinner_mode, array( 'searching', 'focus', 'hidden' ), true ) ) {
			$spinner_mode = 'searching';
		}

		$classes = array( 'zymarg-algolia-wrapper' );
		if ( $stretch )     $classes[] = 'zymarg-stretch';
		if ( $no_dropdown ) $classes[] = 'zymarg-no-dropdown';
		if ( $no_empty )    $classes[] = 'zymarg-no-empty';
		if ( $no_clear )    $classes[] = 'zymarg-no-clear';
		if ( $clear_left )  $classes[] = 'zymarg-clear-left';
		$wrap_cls = implode( ' ', $classes );

		// Section visibility flags as data attributes; JS reads these per wrapper.
		$data_attrs = ' data-show-products="' . ( $show_products ? '1' : '0' ) . '"' .
			' data-show-categories="' . ( $show_categories ? '1' : '0' ) . '"' .
			' data-show-vendors="' . ( $show_vendors ? '1' : '0' ) . '"' .
			' data-category-scope="' . ( $category_scope ? '1' : '0' ) . '"' .
			' data-spinner-mode="' . esc_attr( $spinner_mode ) . '"';

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrap_cls ); ?>" data-zymarg-search<?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
						<svg viewBox="0 0 24 24" aria-hidden="true">
							<path d="M6 6l12 12M18 6L6 18" stroke="currentColor"
								stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
			</form>

			<div class="zymarg-algolia-dropdown" role="listbox" hidden>
				<div class="zymarg-algolia-loading" hidden>
					<div class="zymarg-algolia-spark-loading">
						<span class="zymarg-spark zymarg-spark--search" role="img" aria-label="Loading search results">
							<svg class="zymarg-spark__svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
								<g class="zymarg-spark-group--accent">
									<path class="zymarg-spark-item--purple" d="M10.4 5.4c0 1.32-0.24 2.4-1.44 2.4 1.2 0 1.44 1.08 1.44 2.4 0-1.32 0.24-2.4 1.44-2.4-1.2 0-1.44-1.08-1.44-2.4z"/>
									<path class="zymarg-spark-item--gold" d="M10.4 6.0c0 0.96-0.18 1.8-1.08 1.8 0.9 0 1.08 0.84 1.08 1.8 0-0.9 0.18-1.8 1.08-1.8-0.9 0-1.08-0.84-1.08-1.8z"/>
								</g>
								<g class="zymarg-spark-group--companion">
									<path class="zymarg-spark-item--purple" d="M9.5 10.92c0 2.25-0.45 4.12-2.4 4.12 1.95 0 2.4 1.87 2.4 4.12 0-2.25 0.45-4.12 2.4-4.12-1.95 0-2.4-1.87-2.4-4.12z"/>
									<path class="zymarg-spark-item--gold" d="M9.5 11.5c0 1.9-0.38 3.54-2.0 3.54 1.62 0 2.0 1.64 2.0 3.54 0-1.9 0.38-3.54 2.0-3.54-1.62 0-2.0-1.64-2.0-3.54z"/>
								</g>
								<g class="zymarg-spark-group--hero">
									<path class="zymarg-spark-item--purple" d="M15.2 5.6c0 3.45-0.69 6.3-4.08 6.3 3.39 0 4.08 2.85 4.08 6.3 0-3.45 0.69-6.3 4.08-6.3-3.39 0-4.08-2.85-4.08-6.3z"/>
									<path class="zymarg-spark-item--gold" d="M15.2 6.5c0 2.9-0.58 5.4-3.39 5.4 2.81 0 3.39 2.5 3.39 5.4 0-2.9 0.58-5.4 3.39-5.4-2.81 0-3.39-2.5-3.39-5.4z"/>
								</g>
							</svg>
						</span>
					</div>
					<div class="zymarg-algolia-shimmer-list">
						<div class="zymarg-shimmer-row">
							<div class="zymarg-shimmer-img"></div>
							<div class="zymarg-shimmer-body">
								<div class="zymarg-shimmer-line zymarg-shimmer-line--title"></div>
								<div class="zymarg-shimmer-line zymarg-shimmer-line--meta"></div>
							</div>
							<div class="zymarg-shimmer-price"></div>
						</div>
						<div class="zymarg-shimmer-row">
							<div class="zymarg-shimmer-img"></div>
							<div class="zymarg-shimmer-body">
								<div class="zymarg-shimmer-line zymarg-shimmer-line--title zymarg-shimmer-line--short"></div>
								<div class="zymarg-shimmer-line zymarg-shimmer-line--meta"></div>
							</div>
							<div class="zymarg-shimmer-price"></div>
						</div>
						<div class="zymarg-shimmer-row">
							<div class="zymarg-shimmer-img"></div>
							<div class="zymarg-shimmer-body">
								<div class="zymarg-shimmer-line zymarg-shimmer-line--title"></div>
								<div class="zymarg-shimmer-line zymarg-shimmer-line--meta zymarg-shimmer-line--short"></div>
							</div>
							<div class="zymarg-shimmer-price"></div>
						</div>
					</div>
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
