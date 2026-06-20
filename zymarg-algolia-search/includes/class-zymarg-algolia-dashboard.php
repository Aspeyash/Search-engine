<?php
/**
 * WP Admin Dashboard widget — ZYMARG Algolia Search Stats.
 *
 * Shows:
 *   - Total indexed items (products + vendors + categories)
 *   - Last index update time
 *   - Top 20 recent search queries (from Algolia Analytics API)
 *   - Searches with zero results
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Dashboard
 */
class Zymarg_Algolia_Dashboard {

	/**
	 * Transient key for analytics cache (avoid hammering Algolia).
	 */
	const ANALYTICS_CACHE_KEY = 'zymarg_algolia_analytics';
	const ANALYTICS_CACHE_TTL = 30 * MINUTE_IN_SECONDS;

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		// Track last index update time.
		add_action( 'zymarg_algolia_reindex_batch_products', array( $this, 'touch_last_update' ), 99 );
		add_action( 'zymarg_algolia_reindex_batch_vendors', array( $this, 'touch_last_update' ), 99 );
		add_action( 'zymarg_algolia_reindex_batch_categories', array( $this, 'touch_last_update' ), 99 );
		add_action( 'shutdown', array( $this, 'maybe_touch_on_single_index' ) );
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'zymarg_algolia_dashboard',
			'🔍 ZYMARG Algolia Search — Stats & Analytics',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the widget.
	 */
	public function render() {
		$client = new Zymarg_Algolia_Client();
		if ( ! $client->is_configured() ) {
			echo '<p style="color:#b00;">Algolia credentials are not configured. <a href="' . esc_url( admin_url( 'options-general.php?page=zymarg-algolia' ) ) . '">Open settings</a></p>';
			return;
		}

		// --- Search Engine 2.0 feature guide (the "remember everything" note) ---
		$this->render_feature_guide();

		// --- Index stats ---
		$stats = $this->get_index_stats( $client );
		$last_update = get_option( 'zymarg_algolia_last_index_update', 0 );

		echo '<div class="zymarg-dash-stats">';
		echo '<h4 style="margin:0 0 8px;">Index Overview</h4>';
		echo '<table class="widefat striped" style="margin-bottom:16px;">';
		echo '<thead><tr><th>Index</th><th style="text-align:right;">Records</th></tr></thead>';
		echo '<tbody>';

		$total = 0;
		foreach ( $stats as $name => $count ) {
			$total += $count;
			$label = ucfirst( str_replace( zymarg_algolia_get_setting( 'index_prefix', 'zymarg_' ), '', $name ) );
			echo '<tr><td>' . esc_html( $label ) . '</td><td style="text-align:right;font-weight:700;">' . number_format_i18n( $count ) . '</td></tr>';
		}
		echo '<tr style="background:#f9f5ff;"><td><strong>Total</strong></td><td style="text-align:right;font-weight:700;">' . number_format_i18n( $total ) . '</td></tr>';
		echo '</tbody></table>';

		if ( $last_update ) {
			$human = human_time_diff( $last_update, time() ) . ' ago';
			$exact = wp_date( 'M j, Y g:i A', $last_update );
			echo '<p style="color:#666;font-size:12px;margin:0 0 14px;">Last index update: <strong>' . esc_html( $exact ) . '</strong> (' . esc_html( $human ) . ')</p>';
		} else {
			echo '<p style="color:#666;font-size:12px;margin:0 0 14px;">Last index update: <em>Not yet recorded</em></p>';
		}
		echo '</div>';

		// --- Analytics ---
		$analytics = $this->get_analytics( $client );

		echo '<div class="zymarg-dash-analytics">';

		// Top searches.
		echo '<h4 style="margin:14px 0 8px;">Top 20 Search Queries (Last 7 Days)</h4>';
		if ( ! empty( $analytics['top_searches'] ) ) {
			echo '<table class="widefat striped"><thead><tr><th>#</th><th>Query</th><th style="text-align:right;">Searches</th></tr></thead><tbody>';
			$i = 1;
			foreach ( array_slice( $analytics['top_searches'], 0, 20 ) as $item ) {
				$query = isset( $item['search'] ) ? $item['search'] : ( isset( $item['query'] ) ? $item['query'] : '—' );
				$count = isset( $item['count'] ) ? (int) $item['count'] : ( isset( $item['nbSearches'] ) ? (int) $item['nbSearches'] : 0 );
				echo '<tr><td>' . $i . '</td><td>' . esc_html( $query ) . '</td><td style="text-align:right;">' . number_format_i18n( $count ) . '</td></tr>';
				$i++;
			}
			echo '</tbody></table>';
		} else {
			echo '<p style="color:#888;font-size:13px;">No search data yet. Analytics appear once users start searching.</p>';
		}

		// Zero-result searches.
		echo '<h4 style="margin:18px 0 8px;color:#b00;">Searches With Zero Results (Last 7 Days)</h4>';
		echo '<p style="font-size:12px;color:#666;margin:0 0 6px;">These are products people searched for but couldn\'t find. Consider adding them to your store!</p>';
		if ( ! empty( $analytics['no_results'] ) ) {
			echo '<table class="widefat striped"><thead><tr><th>#</th><th>Query</th><th style="text-align:right;">Searches</th></tr></thead><tbody>';
			$i = 1;
			foreach ( array_slice( $analytics['no_results'], 0, 20 ) as $item ) {
				$query = isset( $item['search'] ) ? $item['search'] : ( isset( $item['query'] ) ? $item['query'] : '—' );
				$count = isset( $item['count'] ) ? (int) $item['count'] : ( isset( $item['nbSearches'] ) ? (int) $item['nbSearches'] : 0 );
				echo '<tr><td>' . $i . '</td><td><strong>' . esc_html( $query ) . '</strong></td><td style="text-align:right;">' . number_format_i18n( $count ) . '</td></tr>';
				$i++;
			}
			echo '</tbody></table>';
		} else {
			echo '<p style="color:#388e3c;font-size:13px;">Great! No zero-result searches recorded. Your catalog covers what users are looking for.</p>';
		}

		// Locally-captured no-results (free-tier friendly, independent of Algolia retention).
		$this->render_local_no_results();

		echo '</div>';

		// Refresh link.
		$refresh_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=zymarg_algolia_refresh_analytics' ),
			'zymarg_algolia_refresh_analytics'
		);
		echo '<p style="margin:14px 0 0;text-align:right;">';
		echo '<a class="button button-small" href="' . esc_url( $refresh_url ) . '">Refresh analytics</a>';
		echo '</p>';
	}

	/* ---------------------------------------------------------------------- */
	/* Feature guide + local no-results.                                       */
	/* ---------------------------------------------------------------------- */

	/**
	 * Render the "Search Engine 2.0" feature guide — a plain-language note of
	 * every smart feature and whether it is currently ON or OFF. This is the
	 * single place to "remember everything" the search bar can do.
	 */
	protected function render_feature_guide() {
		$settings_url = admin_url( 'options-general.php?page=zymarg-algolia' );

		$features = array(
			array(
				'key'   => 'feat_fast',
				'name'  => 'Faster results (cache + no flicker)',
				'desc'  => 'Remembers recent queries in memory and ignores out-of-order responses, so the dropdown never flickers and repeat searches feel instant.',
			),
			array(
				'key'   => 'feat_keyboard',
				'name'  => 'Keyboard navigation',
				'desc'  => 'Move through the dropdown with the Up/Down arrow keys and open the highlighted result with Enter. Uses the existing highlight style — no visual change.',
			),
			array(
				'key'   => 'feat_recent',
				'name'  => 'Recent searches',
				'desc'  => "Shows a visitor their last few searches when they click an empty search box. Stored privately in that visitor's own browser — nothing is sent to your server.",
			),
			array(
				'key'   => 'feat_no_results_log',
				'name'  => 'No-results logging',
				'desc'  => 'Records searches that returned nothing so you can see what shoppers want but cannot find. The captured list appears further down in this widget. Works on the Algolia free tier.',
			),
			array(
				'key'   => 'feat_insights',
				'name'  => 'Algolia Insights (smarter ranking)',
				'desc'  => 'Sends anonymous click events to Algolia so results gradually improve based on what people actually click. Opt-in. Works on the free tier.',
			),
			array(
				'key'   => 'feat_suggestions',
				'name'  => 'Query Suggestions',
				'desc'  => 'Shows as-you-type search suggestions above the results. Requires a Query Suggestions index created in your Algolia dashboard, with its name entered in settings.',
			),
		);

		echo '<div class="zymarg-dash-guide" style="margin:0 0 18px;padding:12px 14px;background:#f9f5ff;border:1px solid #e6d9ff;border-radius:8px;">';
		echo '<h4 style="margin:0 0 4px;">Search Engine 2.0 — Feature Guide</h4>';
		echo '<p style="margin:0 0 10px;font-size:12px;color:#555;">What your search bar can do, and what is switched on right now. Change any of these in <a href="' . esc_url( $settings_url ) . '">Settings → ZYMARG Algolia</a>.</p>';

		echo '<table class="widefat striped" style="margin:0;"><thead><tr>'
			. '<th>Feature</th><th style="width:90px;text-align:center;">Status</th><th>What it does</th>'
			. '</tr></thead><tbody>';

		foreach ( $features as $f ) {
			$on = ! empty( zymarg_algolia_get_setting( $f['key'] ) );

			// Query Suggestions also needs an index name to actually run.
			$note = '';
			if ( 'feat_suggestions' === $f['key'] && $on ) {
				$idx = trim( (string) zymarg_algolia_get_setting( 'suggestions_index' ) );
				if ( '' === $idx ) {
					$note = ' <em style="color:#b26a00;">(on, but no suggestions index set yet)</em>';
				}
			}

			if ( $on ) {
				$badge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#e3f6e8;color:#1a7f37;font-weight:700;font-size:11px;">ON</span>';
			} else {
				$badge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f0f0f0;color:#888;font-weight:700;font-size:11px;">OFF</span>';
			}

			echo '<tr>';
			echo '<td style="font-weight:600;">' . esc_html( $f['name'] ) . '</td>';
			echo '<td style="text-align:center;">' . $badge . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
			echo '<td style="font-size:12px;color:#555;">' . esc_html( $f['desc'] ) . wp_kses_post( $note ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render the locally-captured zero-result queries (from the frontend logger).
	 */
	protected function render_local_no_results() {
		if ( empty( zymarg_algolia_get_setting( 'feat_no_results_log' ) ) ) {
			return;
		}

		$rows = Zymarg_Algolia_Logger::get_top( 20 );

		echo '<h4 style="margin:18px 0 8px;">No-Results Searches Captured On This Site</h4>';
		echo '<p style="font-size:12px;color:#666;margin:0 0 6px;">Recorded directly by the plugin (independent of Algolia analytics). Great for spotting products to add.</p>';

		if ( empty( $rows ) ) {
			echo '<p style="color:#388e3c;font-size:13px;">Nothing captured yet — no empty searches recorded.</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Query</th><th style="text-align:right;">Times</th><th style="text-align:right;">Last seen</th></tr></thead><tbody>';
		$i = 1;
		foreach ( $rows as $row ) {
			$q     = isset( $row['q'] ) ? $row['q'] : '—';
			$count = isset( $row['c'] ) ? (int) $row['c'] : 0;
			$when  = isset( $row['t'] ) ? human_time_diff( (int) $row['t'], time() ) . ' ago' : '—';
			echo '<tr>';
			echo '<td>' . (int) $i . '</td>';
			echo '<td><strong>' . esc_html( $q ) . '</strong></td>';
			echo '<td style="text-align:right;">' . number_format_i18n( $count ) . '</td>';
			echo '<td style="text-align:right;color:#888;">' . esc_html( $when ) . '</td>';
			echo '</tr>';
			$i++;
		}
		echo '</tbody></table>';

		$clear_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=zymarg_algolia_clear_no_results' ),
			'zymarg_algolia_clear_no_results'
		);
		echo '<p style="margin:8px 0 0;text-align:right;">';
		echo '<a class="button button-small" href="' . esc_url( $clear_url ) . '" onclick="return confirm(\'Clear all captured no-results searches?\');">Clear captured list</a>';
		echo '</p>';
	}

	/* ---------------------------------------------------------------------- */
	/* Data helpers.                                                           */
	/* ---------------------------------------------------------------------- */

	/**
	 * Get record counts for each index.
	 *
	 * @param Zymarg_Algolia_Client $client Client.
	 * @return array Index name => count.
	 */
	protected function get_index_stats( $client ) {
		$result = $client->verify(); // GET /1/indexes — returns list with nbRecords.
		$stats  = array();

		$prefix = zymarg_algolia_get_setting( 'index_prefix', 'zymarg_' );
		$our    = array(
			$prefix . 'products',
			$prefix . 'vendors',
			$prefix . 'categories',
		);

		if ( is_array( $result ) && ! empty( $result['items'] ) ) {
			foreach ( $result['items'] as $idx ) {
				$name = isset( $idx['name'] ) ? $idx['name'] : '';
				if ( in_array( $name, $our, true ) ) {
					$stats[ $name ] = isset( $idx['entries'] ) ? (int) $idx['entries'] : 0;
				}
			}
		}

		// Ensure all 3 appear even if not yet created.
		foreach ( $our as $name ) {
			if ( ! isset( $stats[ $name ] ) ) {
				$stats[ $name ] = 0;
			}
		}

		return $stats;
	}

	/**
	 * Fetch search analytics from Algolia (cached).
	 *
	 * Uses the Analytics API:
	 *   GET /2/searches (top searches)
	 *   GET /2/searches/noResults (zero-result searches)
	 *
	 * @param Zymarg_Algolia_Client $client Client.
	 * @return array
	 */
	protected function get_analytics( $client ) {
		$cached = get_transient( self::ANALYTICS_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$app_id    = zymarg_algolia_get_setting( 'app_id' );
		$admin_key = zymarg_algolia_get_setting( 'admin_api_key' );
		$index     = zymarg_algolia_index_name( 'products' );

		$data = array(
			'top_searches' => array(),
			'no_results'   => array(),
		);

		if ( empty( $app_id ) || empty( $admin_key ) ) {
			return $data;
		}

		$headers = array(
			'X-Algolia-Application-Id' => $app_id,
			'X-Algolia-API-Key'        => $admin_key,
			'Accept'                   => 'application/json',
			'User-Agent'               => 'ZymargAlgolia/' . ZYMARG_ALGOLIA_VERSION,
		);

		$base_url = 'https://analytics.algolia.com';
		$end_date = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		// Top searches.
		$url = $base_url . '/2/searches?index=' . rawurlencode( $index )
			. '&startDate=' . $start_date
			. '&endDate=' . $end_date
			. '&limit=20&orderBy=searchCount';

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => $headers,
		) );

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $body ) && isset( $body['searches'] ) ) {
				$data['top_searches'] = $body['searches'];
			}
		}

		// No-result searches.
		$url = $base_url . '/2/searches/noResults?index=' . rawurlencode( $index )
			. '&startDate=' . $start_date
			. '&endDate=' . $end_date
			. '&limit=20&orderBy=searchCount';

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => $headers,
		) );

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $body ) && isset( $body['searches'] ) ) {
				$data['no_results'] = $body['searches'];
			}
		}

		set_transient( self::ANALYTICS_CACHE_KEY, $data, self::ANALYTICS_CACHE_TTL );
		return $data;
	}

	/* ---------------------------------------------------------------------- */
	/* Timestamp tracking.                                                    */
	/* ---------------------------------------------------------------------- */

	/**
	 * Record the current time as "last index update."
	 */
	public function touch_last_update() {
		update_option( 'zymarg_algolia_last_index_update', time(), false );
	}

	/**
	 * On shutdown, if a single-item index happened this request, record time.
	 * Uses a static flag set by the indexer.
	 */
	public function maybe_touch_on_single_index() {
		if ( did_action( 'zymarg_algolia_indexed_single' ) ) {
			$this->touch_last_update();
		}
	}

	/**
	 * Handle the "Refresh analytics" button.
	 */
	public static function handle_refresh() {
		check_admin_referer( 'zymarg_algolia_refresh_analytics' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		delete_transient( self::ANALYTICS_CACHE_KEY );
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * Handle the "Clear captured list" button (local no-results log).
	 */
	public static function handle_clear_no_results() {
		check_admin_referer( 'zymarg_algolia_clear_no_results' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		Zymarg_Algolia_Logger::clear();
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}
}

add_action( 'admin_post_zymarg_algolia_refresh_analytics', array( 'Zymarg_Algolia_Dashboard', 'handle_refresh' ) );
add_action( 'admin_post_zymarg_algolia_clear_no_results', array( 'Zymarg_Algolia_Dashboard', 'handle_clear_no_results' ) );
