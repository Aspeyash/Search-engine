<?php
/**
 * Frontend: enqueue Algolia InstantSearch.js + render search bar markup.
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

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		// Optional: hook into Astra header to auto-render. Disabled by default; user uses shortcode.
	}

	/**
	 * Should we load assets on this page?
	 *
	 * @return bool
	 */
	protected function should_load() {
		// Always load for now; very small footprint. Can be filtered.
		return (bool) apply_filters( 'zymarg_algolia_should_enqueue', true );
	}

	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}
		$app_id     = zymarg_algolia_get_setting( 'app_id' );
		$search_key = zymarg_algolia_get_setting( 'search_api_key' );

		// Don't enqueue if not configured (avoid console errors).
		if ( empty( $app_id ) || empty( $search_key ) ) {
			return;
		}

		// Algolia search client (UMD).
		wp_register_script(
			'algoliasearch',
			'https://cdn.jsdelivr.net/npm/algoliasearch@4.23.3/dist/algoliasearch-lite.umd.js',
			array(),
			'4.23.3',
			true
		);

		// Algolia Insights (only when the feature is enabled).
		$insights_on = ! empty( zymarg_algolia_get_setting( 'feat_insights' ) );
		if ( $insights_on ) {
			wp_register_script(
				'search-insights',
				'https://cdn.jsdelivr.net/npm/search-insights@2.13.0/dist/search-insights.min.js',
				array(),
				'2.13.0',
				true
			);
		}

		$deps = array( 'algoliasearch' );
		if ( $insights_on ) {
			$deps[] = 'search-insights';
		}

		wp_register_script(
			'zymarg-algolia-search',
			ZYMARG_ALGOLIA_URL . 'assets/js/zymarg-search.js',
			$deps,
			ZYMARG_ALGOLIA_VERSION,
			true
		);

		wp_register_style(
			'zymarg-algolia-search',
			ZYMARG_ALGOLIA_URL . 'assets/css/zymarg-search.css',
			array(),
			ZYMARG_ALGOLIA_VERSION
		);

		wp_localize_script(
			'zymarg-algolia-search',
			'ZymargAlgolia',
			array(
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
				'currencySym'   => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '$',

				// --- Search Engine 2.0 feature flags ---
				'features'      => array(
					'fast'        => ! empty( zymarg_algolia_get_setting( 'feat_fast' ) ),
					'keyboard'    => ! empty( zymarg_algolia_get_setting( 'feat_keyboard' ) ),
					'recent'      => ! empty( zymarg_algolia_get_setting( 'feat_recent' ) ),
					'insights'    => ! empty( zymarg_algolia_get_setting( 'feat_insights' ) ),
					'logNoResults'=> ! empty( zymarg_algolia_get_setting( 'feat_no_results_log' ) ),
					'suggestions' => ! empty( zymarg_algolia_get_setting( 'feat_suggestions' ) ) && '' !== trim( (string) zymarg_algolia_get_setting( 'suggestions_index' ) ),
				),
				'suggestionsIndex' => zymarg_algolia_get_setting( 'suggestions_index' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'logNonce'      => wp_create_nonce( 'zymarg_algolia_log_no_results' ),
				'i18nExtra'     => array(
					'recent'      => __( 'Recent searches', 'zymarg-algolia' ),
					'suggestions' => __( 'Suggestions', 'zymarg-algolia' ),
					'clearRecent' => __( 'Clear', 'zymarg-algolia' ),
				),
			)
		);

		wp_enqueue_style( 'zymarg-algolia-search' );
		wp_enqueue_script( 'zymarg-algolia-search' );
	}

	/**
	 * Render the search bar HTML. Used by shortcode.
	 *
	 * @return string
	 */
	public static function render_html() {
		ob_start();
		?>
		<div class="zymarg-algolia-wrapper" data-zymarg-search>
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
