<?php
/**
 * Search-results-page CTA banner.
 *
 * When the global "CTA Mode" setting is set to "search_page", this class
 * injects a "Couldn't find what you're looking for? Request Here" banner
 * **below the WP search results** (regardless of whether any results
 * matched). The banner uses CSS variables driven by the plugin's settings
 * page so the user can fully customize its size, padding, margins,
 * alignment and colors without writing CSS.
 *
 * The banner is rendered after the main search loop ends. Hooks tried in
 * order (first to fire wins; double-output prevented by the $rendered
 * flag):
 *
 *   1. `loop_end` — universal WP hook, fires after the main posts loop
 *   2. `astra_content_bottom` — Astra fallback if `loop_end` is suppressed
 *
 * Also exposes `[zymarg_search_cta]` for manual placement on Elementor Pro
 * custom search templates / Astra Theme Builder pages where the auto-inject
 * hooks may not fire.
 *
 * Display logic:
 *   - cta_mode = 'dropdown'     : banner hidden everywhere; dropdown CTA shows
 *   - cta_mode = 'search_page'  : banner auto-injects on /?s= page; dropdown CTA hidden
 *   - cta_mode = 'hidden'       : banner hidden everywhere; dropdown CTA also hidden
 *
 * @package ZymargAlgolia
 * @since   1.0.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Search_CTA
 */
class Zymarg_Algolia_Search_CTA {

	/**
	 * Whether the banner has been rendered on this request.
	 *
	 * Prevents double-output when both `loop_end` and `astra_content_bottom`
	 * fire on the same page.
	 *
	 * @var bool
	 */
	protected $rendered = false;

	public function __construct() {
		// Auto-inject after the search loop. Both hooks are tried; the first
		// one that runs sets $rendered=true and the other becomes a no-op.
		add_action( 'loop_end', array( $this, 'maybe_render_after_loop' ) );
		add_action( 'astra_content_bottom', array( $this, 'maybe_render_astra' ) );

		// Manual placement.
		add_shortcode( 'zymarg_search_cta', array( $this, 'shortcode' ) );

		// Make sure the search bar's CSS is enqueued on the search results
		// page even when no widget is present (the banner reuses the same
		// stylesheet).
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_styles' ), 20 );
	}

	/* ------------------------------------------------------------------ */
	/* Hooks                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Render the banner at the end of the main search loop.
	 *
	 * @param WP_Query $query The query that just finished looping.
	 */
	public function maybe_render_after_loop( $query ) {
		if ( $this->rendered ) {
			return;
		}
		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) ) {
			return;
		}
		if ( ! $query->is_main_query() || ! is_search() ) {
			return;
		}
		if ( ! $this->is_search_page_mode() ) {
			return;
		}
		$this->rendered = true;
		echo $this->render_banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Astra-specific fallback hook.
	 */
	public function maybe_render_astra() {
		if ( $this->rendered ) {
			return;
		}
		if ( ! is_search() ) {
			return;
		}
		if ( ! $this->is_search_page_mode() ) {
			return;
		}
		$this->rendered = true;
		echo $this->render_banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Manual placement via shortcode.
	 *
	 * @return string
	 */
	public function shortcode() {
		// Respect the master kill switch — when the user explicitly sets the
		// mode to "Hidden everywhere", even shortcodes don't render.
		if ( 'hidden' === $this->current_mode() ) {
			return '';
		}
		return $this->render_banner_html();
	}

	/**
	 * Make sure our stylesheet is on the search results page even when no
	 * search widget is rendered there.
	 */
	public function maybe_enqueue_styles() {
		if ( ! is_search() ) {
			return;
		}
		if ( ! $this->is_search_page_mode() ) {
			return;
		}
		wp_enqueue_style( Zymarg_Algolia_Frontend::STYLE_HANDLE );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * @return string Current cta_mode setting.
	 */
	protected function current_mode() {
		return zymarg_algolia_get_setting( 'cta_mode', 'dropdown' );
	}

	/**
	 * @return bool True when banner should auto-inject on search results page.
	 */
	protected function is_search_page_mode() {
		return 'search_page' === $this->current_mode();
	}

	/**
	 * Build the banner HTML with inline CSS variables from settings.
	 *
	 * @return string
	 */
	protected function render_banner_html() {
		$msg = zymarg_algolia_get_setting( 'no_results_text', "Couldn't find what you're looking for?" );
		$btn = zymarg_algolia_get_setting( 'request_btn', 'Request Here' );
		$url = zymarg_algolia_get_setting( 'community_url', home_url( '/community' ) );

		$styles = array(
			'--zymarg-cta-max-width'     => intval( zymarg_algolia_get_setting( 'cta_max_width', 800 ) ) . 'px',
			'--zymarg-cta-padding-y'     => intval( zymarg_algolia_get_setting( 'cta_padding_y', 32 ) ) . 'px',
			'--zymarg-cta-padding-x'     => intval( zymarg_algolia_get_setting( 'cta_padding_x', 32 ) ) . 'px',
			'--zymarg-cta-margin-top'    => intval( zymarg_algolia_get_setting( 'cta_margin_top', 40 ) ) . 'px',
			'--zymarg-cta-margin-bottom' => intval( zymarg_algolia_get_setting( 'cta_margin_bottom', 40 ) ) . 'px',
			'--zymarg-cta-radius'        => intval( zymarg_algolia_get_setting( 'cta_radius', 14 ) ) . 'px',
			'--zymarg-cta-text-size'     => intval( zymarg_algolia_get_setting( 'cta_text_size', 18 ) ) . 'px',
			'--zymarg-cta-btn-size'      => intval( zymarg_algolia_get_setting( 'cta_btn_size', 16 ) ) . 'px',
			'--zymarg-cta-bg'            => $this->safe_color( zymarg_algolia_get_setting( 'cta_bg', '#ffffff' ) ),
			'--zymarg-cta-text'          => $this->safe_color( zymarg_algolia_get_setting( 'cta_text_color', '#1a1a1a' ) ),
			'--zymarg-cta-btn-bg'        => $this->safe_color( zymarg_algolia_get_setting( 'cta_btn_bg', '#7B3FE4' ) ),
			'--zymarg-cta-btn-color'     => $this->safe_color( zymarg_algolia_get_setting( 'cta_btn_color', '#ffffff' ) ),
			'--zymarg-cta-align'         => $this->safe_align( zymarg_algolia_get_setting( 'cta_align', 'center' ) ),
		);

		$style_pairs = array();
		foreach ( $styles as $k => $v ) {
			$style_pairs[] = $k . ':' . $v;
		}
		$style_attr = implode( ';', $style_pairs );

		ob_start();
		?>
		<div class="zymarg-algolia-cta-banner" role="complementary" style="<?php echo esc_attr( $style_attr ); ?>">
			<p class="zymarg-algolia-cta-text"><?php echo esc_html( $msg ); ?></p>
			<a class="zymarg-algolia-cta-btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $btn ); ?></a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Light color sanitization (hex / rgb / rgba / hsl / hsla / keyword).
	 * Falls back to the input if it doesn't match a known shape — `style=""`
	 * already escapes via esc_attr(), but we strip anything that could break
	 * the attribute.
	 */
	protected function safe_color( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '#ffffff';
		}
		if ( preg_match( '/[<>"\'`]/', $value ) ) {
			return '#ffffff';
		}
		return $value;
	}

	protected function safe_align( $value ) {
		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'center';
	}
}
