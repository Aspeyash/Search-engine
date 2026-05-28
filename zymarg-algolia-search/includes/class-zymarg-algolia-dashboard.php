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
}

add_action( 'admin_post_zymarg_algolia_refresh_analytics', array( 'Zymarg_Algolia_Dashboard', 'handle_refresh' ) );
