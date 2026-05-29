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

		// Diagnostic footer (1.0.14): shows which Algolia analytics region
		// the dashboard ended up using + last fetch time + any error. Helps
		// spot region/credential issues at a glance.
		if ( ! empty( $analytics['_meta'] ) ) {
			$meta   = $analytics['_meta'];
			$region = isset( $meta['region'] ) ? $meta['region'] : null;
			$err    = isset( $meta['error'] ) ? $meta['error'] : null;
			$at     = isset( $meta['fetched_at'] ) ? (int) $meta['fetched_at'] : 0;

			echo '<div style="margin-top:14px;padding-top:10px;border-top:1px dashed #ddd;font-size:11px;color:#777;">';
			if ( $err ) {
				echo '<div style="color:#b00;"><strong>Analytics error:</strong> ' . esc_html( $err ) . '</div>';
			}
			if ( $region ) {
				$label = ( 'eu' === $region )
					? 'EU (analytics.de.algolia.com — Germany / UK / EU clusters)'
					: 'Global (analytics.algolia.com — US / Global clusters)';
				echo '<div>Analytics region used: <strong>' . esc_html( $label ) . '</strong></div>';
			} elseif ( ! $err ) {
				echo '<div>Analytics region used: <em>none returned data — Algolia may still be processing your searches (4–24h delay)</em></div>';
			}
			if ( $at ) {
				echo '<div>Last fetched: ' . esc_html( wp_date( 'M j, Y g:i A', $at ) ) . '</div>';
			}
			echo '</div>';
		}

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
	 *   GET /2/searches            (top searches)
	 *   GET /2/searches/noResults  (zero-result searches)
	 *
	 * v1.0.14: Algolia analytics is region-segregated. Apps hosted in the EU
	 * cluster (Germany, France, UK, etc.) have their analytics served from
	 * `analytics.de.algolia.com` instead of the global `analytics.algolia.com`.
	 * Hitting the wrong endpoint returns HTTP 200 with an empty `searches`
	 * array — silently empty, no error. So we try the global endpoint first,
	 * and if it returns empty we automatically fall through to the EU
	 * endpoint. The cache locks onto whichever one returned data so we don't
	 * keep paying the second roundtrip on every render.
	 *
	 * The cache also stores diagnostic info (region used, http status, last
	 * fetched time, last error) so the dashboard can surface clear feedback.
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
			'_meta'        => array(
				'fetched_at'  => time(),
				'region'      => null,
				'error'       => null,
				'http_status' => null,
			),
		);

		if ( empty( $app_id ) || empty( $admin_key ) ) {
			$data['_meta']['error'] = 'Algolia App ID or Admin API Key is missing.';
			return $data;
		}

		// Try endpoints in order. First one that returns at least one search
		// in either bucket wins. If both return empty, we still cache the
		// result (so we don't keep hammering both endpoints) but record the
		// last region attempted so the user knows what we tried.
		$endpoints = array(
			'global' => 'https://analytics.algolia.com',
			'eu'     => 'https://analytics.de.algolia.com',
		);

		// Allow user override via setting.
		$forced_region = zymarg_algolia_get_setting( 'analytics_region', 'auto' );
		if ( 'global' === $forced_region ) {
			$endpoints = array( 'global' => $endpoints['global'] );
		} elseif ( 'eu' === $forced_region ) {
			$endpoints = array( 'eu' => $endpoints['eu'] );
		}

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		$last_error  = null;
		$last_status = null;

		foreach ( $endpoints as $region => $base_url ) {
			$top   = $this->fetch_analytics_path( $base_url, '/2/searches',          $index, $start_date, $end_date, $app_id, $admin_key );
			$noRes = $this->fetch_analytics_path( $base_url, '/2/searches/noResults', $index, $start_date, $end_date, $app_id, $admin_key );

			$last_status = ! empty( $top['status'] ) ? $top['status'] : ( ! empty( $noRes['status'] ) ? $noRes['status'] : null );
			$last_error  = ! empty( $top['error'] )  ? $top['error']  : ( ! empty( $noRes['error'] )  ? $noRes['error']  : null );

			$has_data = ( ! empty( $top['searches'] ) || ! empty( $noRes['searches'] ) );

			if ( $has_data ) {
				$data['top_searches']         = $top['searches'];
				$data['no_results']           = $noRes['searches'];
				$data['_meta']['region']      = $region;
				$data['_meta']['error']       = null;
				$data['_meta']['http_status'] = $last_status;
				break;
			}

			// If we got a non-empty error response, remember it but keep trying.
			if ( $last_error ) {
				$data['_meta']['error']       = $last_error;
				$data['_meta']['http_status'] = $last_status;
				$data['_meta']['region']      = $region;
			}
		}

		set_transient( self::ANALYTICS_CACHE_KEY, $data, self::ANALYTICS_CACHE_TTL );
		return $data;
	}

	/**
	 * Make a single GET request to the Algolia analytics API.
	 *
	 * @param string $base_url   e.g. https://analytics.algolia.com
	 * @param string $path       e.g. /2/searches  or  /2/searches/noResults
	 * @param string $index      Index name.
	 * @param string $start_date YYYY-MM-DD
	 * @param string $end_date   YYYY-MM-DD
	 * @param string $app_id     Algolia App ID.
	 * @param string $admin_key  Algolia Admin API Key.
	 * @return array { searches: array, status: int, error: string|null }
	 */
	protected function fetch_analytics_path( $base_url, $path, $index, $start_date, $end_date, $app_id, $admin_key ) {
		$url = $base_url . $path
			. '?index=' . rawurlencode( $index )
			. '&startDate=' . $start_date
			. '&endDate=' . $end_date
			. '&limit=20&orderBy=searchCount';

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'X-Algolia-Application-Id' => $app_id,
				'X-Algolia-API-Key'        => $admin_key,
				'Accept'                   => 'application/json',
				'User-Agent'               => 'ZymargAlgolia/' . ZYMARG_ALGOLIA_VERSION,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'searches' => array(),
				'status'   => 0,
				'error'    => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			$err = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : ( 'HTTP ' . $status );
			return array(
				'searches' => array(),
				'status'   => $status,
				'error'    => $err,
			);
		}

		$searches = ( is_array( $body ) && isset( $body['searches'] ) && is_array( $body['searches'] ) )
			? $body['searches']
			: array();

		return array(
			'searches' => $searches,
			'status'   => 200,
			'error'    => null,
		);
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
