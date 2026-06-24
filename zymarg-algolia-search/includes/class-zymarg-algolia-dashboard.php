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
	const ANALYTICS_CACHE_KEY = 'zymarg_algolia_analytics_v2';
	const ANALYTICS_CACHE_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Static helper exposed for the frontend (1.0.15+).
	 *
	 * Reads the analytics cache (populated by the dashboard widget on each
	 * admin pageview) and returns the top N search terms as a flat array of
	 * strings, suitable for "Trending searches" pills in the empty-state
	 * dropdown. Never makes a live API call — purely cache-read so it adds
	 * zero latency to every public page render.
	 *
	 * @param int $limit Max results to return.
	 * @return array<string>
	 */
	public static function get_cached_trending_searches( $limit = 6 ) {
		$cached = get_transient( self::ANALYTICS_CACHE_KEY );
		if ( ! is_array( $cached ) || empty( $cached['top_searches'] ) || ! is_array( $cached['top_searches'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $cached['top_searches'] as $row ) {
			if ( is_array( $row ) && ! empty( $row['search'] ) ) {
				$out[] = (string) $row['search'];
				if ( count( $out ) >= (int) $limit ) {
					break;
				}
			}
		}
		return $out;
	}

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
			'🔍 Search Engine — Stats & Analytics',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the widget.
	 */
	public function render() {
		$client = new Zymarg_Algolia_Client();
		if ( ! $client->is_configured() ) {
			echo '<p style="color:#b00;">Search Engine credentials are not configured. <a href="' . esc_url( admin_url( 'admin.php?page=zymarg-algolia' ) ) . '">Open settings</a></p>';
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

		// 1.0.16: Stat cards row at the top.
		$ctr_pct    = ( $analytics['ctr_rate'] !== null ) ? round( $analytics['ctr_rate'] * 100, 1 ) : null;
		$avg_pos    = ( $analytics['avg_click_position'] !== null ) ? round( $analytics['avg_click_position'], 1 ) : null;
		$volume     = ( $analytics['search_volume_total'] !== null ) ? (int) $analytics['search_volume_total'] : null;
		$no_click_n = is_array( $analytics['no_clicks'] ) ? count( $analytics['no_clicks'] ) : 0;

		$daily_points = array();
		foreach ( (array) $analytics['search_volume_dates'] as $d ) {
			if ( isset( $d['count'] ) ) {
				$daily_points[] = (int) $d['count'];
			}
		}

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin:0 0 16px;">';

		echo '<div style="background:#faf7ff;border:1px solid #ece4ff;border-radius:8px;padding:12px;">';
		echo '<div style="font-size:22px;font-weight:700;color:#1a1a1a;">' . ( $volume === null ? '<span style="color:#bbb;font-size:14px;">&mdash;</span>' : number_format_i18n( $volume ) ) . '</div>';
		echo '<div style="font-size:11px;color:#777;margin-top:2px;text-transform:uppercase;letter-spacing:0.04em;">Searches (7d)</div>';
		if ( ! empty( $daily_points ) ) {
			echo $this->render_sparkline( $daily_points );
		}
		echo '</div>';

		echo '<div style="background:#faf7ff;border:1px solid #ece4ff;border-radius:8px;padding:12px;">';
		echo '<div style="font-size:22px;font-weight:700;color:#1a1a1a;">' . ( $ctr_pct === null ? '<span style="color:#bbb;font-size:14px;">&mdash;</span>' : esc_html( $ctr_pct ) . '<span style="font-size:14px;color:#777;">%</span>' ) . '</div>';
		echo '<div style="font-size:11px;color:#777;margin-top:2px;text-transform:uppercase;letter-spacing:0.04em;">Click-through rate</div>';
		if ( $analytics['ctr_count'] !== null && $analytics['ctr_total'] !== null ) {
			echo '<div style="font-size:11px;color:#999;margin-top:6px;">' . number_format_i18n( (int) $analytics['ctr_count'] ) . ' clicks / ' . number_format_i18n( (int) $analytics['ctr_total'] ) . ' searches</div>';
		}
		echo '</div>';

		echo '<div style="background:#faf7ff;border:1px solid #ece4ff;border-radius:8px;padding:12px;">';
		echo '<div style="font-size:22px;font-weight:700;color:#1a1a1a;">' . ( $avg_pos === null ? '<span style="color:#bbb;font-size:14px;">&mdash;</span>' : esc_html( $avg_pos ) ) . '</div>';
		echo '<div style="font-size:11px;color:#777;margin-top:2px;text-transform:uppercase;letter-spacing:0.04em;">Avg click position</div>';
		echo '<div style="font-size:11px;color:#999;margin-top:6px;">Lower = top results more relevant</div>';
		echo '</div>';

		$noclick_color = $no_click_n > 0 ? '#b85a00' : '#388e3c';
		echo '<div style="background:#faf7ff;border:1px solid #ece4ff;border-radius:8px;padding:12px;">';
		echo '<div style="font-size:22px;font-weight:700;color:' . $noclick_color . ';">' . esc_html( number_format_i18n( $no_click_n ) ) . '</div>';
		echo '<div style="font-size:11px;color:#777;margin-top:2px;text-transform:uppercase;letter-spacing:0.04em;">No-click queries</div>';
		echo '<div style="font-size:11px;color:#999;margin-top:6px;">Hits but no clicks</div>';
		echo '</div>';

		echo '</div>';

		// 1.0.16: Health check section — purely local, zero API calls.
		$checks = $this->get_health_checks( $client );
		echo '<details style="margin:0 0 16px;border:1px solid #e2d5ff;border-radius:8px;padding:6px 12px;background:#fff;">';
		echo '<summary style="cursor:pointer;font-weight:600;font-size:13px;color:#444;padding:6px 0;">Health check (' . count( $checks ) . ')</summary>';
		echo '<ul style="margin:6px 0 8px;padding-left:0;list-style:none;font-size:12px;">';
		foreach ( $checks as $c ) {
			$icon = $c['level'] === 'ok' ? '<span style="color:#388e3c;">&#10003;</span>' : ( $c['level'] === 'warn' ? '<span style="color:#b85a00;">!</span>' : '<span style="color:#b00020;">&#10007;</span>' );
			echo '<li style="padding:3px 0;">' . $icon . ' ' . esc_html( $c['label'] ) . '</li>';
		}
		echo '</ul>';
		echo '</details>';

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

		// 1.0.16: Searches with hits but no clicks — engagement opportunity.
		echo '<h4 style="margin:18px 0 8px;color:#b85a00;">Searches With No Clicks (Last 7 Days)</h4>';
		echo '<p style="font-size:12px;color:#666;margin:0 0 6px;">These searches found products, but nobody clicked. Review the matched products &mdash; titles, photos, or prices may need work.</p>';
		if ( ! empty( $analytics['no_clicks'] ) ) {
			echo '<table class="widefat striped"><thead><tr><th>#</th><th>Query</th><th style="text-align:right;">Searches</th><th style="text-align:right;">Hits</th></tr></thead><tbody>';
			$i = 1;
			foreach ( array_slice( $analytics['no_clicks'], 0, 20 ) as $item ) {
				$query = isset( $item['search'] ) ? $item['search'] : ( isset( $item['query'] ) ? $item['query'] : '\u2014' );
				$count = isset( $item['count'] ) ? (int) $item['count'] : 0;
				$hits  = isset( $item['nbHits'] ) ? (int) $item['nbHits'] : 0;
				echo '<tr><td>' . $i . '</td><td>' . esc_html( $query ) . '</td><td style="text-align:right;">' . number_format_i18n( $count ) . '</td><td style="text-align:right;">' . number_format_i18n( $hits ) . '</td></tr>';
				$i++;
			}
			echo '</tbody></table>';
		} else {
			echo '<p style="color:#388e3c;font-size:13px;">Great! Every search with hits is getting clicks.</p>';
		}

		// 1.0.16: Click position distribution chart.
		if ( ! empty( $analytics['click_positions'] ) ) {
			echo '<h4 style="margin:18px 0 8px;">Click Position Distribution</h4>';
			echo '<p style="font-size:12px;color:#666;margin:0 0 8px;">Where in the result list users click. Most clicks at position 1-3 = top results are highly relevant.</p>';
			$max_count = 0;
			foreach ( $analytics['click_positions'] as $row ) {
				if ( isset( $row['clickCount'] ) && $row['clickCount'] > $max_count ) {
					$max_count = (int) $row['clickCount'];
				}
			}
			if ( $max_count > 0 ) {
				echo '<div style="display:flex;gap:4px;align-items:flex-end;height:80px;background:#fafafa;border-radius:6px;padding:8px;">';
				foreach ( array_slice( $analytics['click_positions'], 0, 15 ) as $row ) {
					$pos = isset( $row['position'] ) ? ( is_array( $row['position'] ) ? implode( '-', $row['position'] ) : (string) $row['position'] ) : '?';
					$cnt = isset( $row['clickCount'] ) ? (int) $row['clickCount'] : 0;
					$h   = (int) ( ( $cnt / $max_count ) * 60 );
					echo '<div style="flex:1;text-align:center;" title="Position ' . esc_attr( $pos ) . ': ' . esc_attr( number_format_i18n( $cnt ) ) . ' clicks">';
					echo '<div style="background:#7B3FE4;border-radius:3px 3px 0 0;height:' . $h . 'px;margin:auto;width:80%;min-height:2px;"></div>';
					echo '<div style="font-size:10px;color:#777;margin-top:4px;">' . esc_html( $pos ) . '</div>';
					echo '</div>';
				}
				echo '</div>';
			}
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
				echo '<div>Analytics region used: <em>none returned data — the search service may still be processing your searches (4–24h delay)</em></div>';
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
			'top_searches'        => array(),
			'no_results'          => array(),
			'no_clicks'           => array(),
			'ctr_rate'            => null,
			'ctr_count'           => null,
			'ctr_total'           => null,
			'avg_click_position'  => null,
			'click_positions'     => array(),
			'search_volume_total' => null,
			'search_volume_dates' => array(),
			'_meta'               => array(
				'fetched_at'  => time(),
				'region'      => null,
				'error'       => null,
				'http_status' => null,
			),
		);

		if ( empty( $app_id ) || empty( $admin_key ) ) {
			$data['_meta']['error'] = 'App ID or Admin API Key is missing.';
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

				// 1.0.16: fetch additional metrics from the same region — admin
				// only, 30-min cached, never touches public-facing search.
				$base_qs = '?index=' . rawurlencode( $index ) .
					'&startDate=' . $start_date .
					'&endDate=' . $end_date;

				$nc = $this->fetch_analytics_json( $base_url, '/2/searches/noClicks' . $base_qs . '&limit=20', $app_id, $admin_key );
				if ( is_array( $nc ) && isset( $nc['searches'] ) && is_array( $nc['searches'] ) ) {
					$data['no_clicks'] = $nc['searches'];
				}

				$ctr = $this->fetch_analytics_json( $base_url, '/2/clicks/clickThroughRate' . $base_qs, $app_id, $admin_key );
				if ( is_array( $ctr ) ) {
					$data['ctr_rate']  = isset( $ctr['rate'] )               ? (float) $ctr['rate']               : null;
					$data['ctr_count'] = isset( $ctr['clickCount'] )         ? (int)   $ctr['clickCount']         : null;
					$data['ctr_total'] = isset( $ctr['trackedSearchCount'] ) ? (int)   $ctr['trackedSearchCount'] : null;
				}

				$avgPos = $this->fetch_analytics_json( $base_url, '/2/clicks/averageClickPosition' . $base_qs, $app_id, $admin_key );
				if ( is_array( $avgPos ) && isset( $avgPos['average'] ) ) {
					$data['avg_click_position'] = (float) $avgPos['average'];
				}

				$pos = $this->fetch_analytics_json( $base_url, '/2/clicks/positions' . $base_qs, $app_id, $admin_key );
				if ( is_array( $pos ) && isset( $pos['positions'] ) && is_array( $pos['positions'] ) ) {
					$data['click_positions'] = $pos['positions'];
				}

				$vol = $this->fetch_analytics_json( $base_url, '/2/searches/count' . $base_qs, $app_id, $admin_key );
				if ( is_array( $vol ) ) {
					$data['search_volume_total'] = isset( $vol['count'] ) ? (int) $vol['count'] : null;
					$data['search_volume_dates'] = isset( $vol['dates'] ) && is_array( $vol['dates'] ) ? $vol['dates'] : array();
				}

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

	/**
	 * Generic Algolia Analytics GET that returns the full parsed JSON body
	 * (or null on failure). Used for endpoints that don't return the
	 * `{searches: [...]}` shape — clickThroughRate, positions, count, etc.
	 *
	 * @param string $base_url   e.g. https://analytics.algolia.com
	 * @param string $path       Path + query string already appended.
	 * @param string $app_id     Algolia App ID.
	 * @param string $admin_key  Algolia Admin API Key.
	 * @return array|null Parsed JSON body, or null if the request failed.
	 */
	protected function fetch_analytics_json( $base_url, $path, $app_id, $admin_key ) {
		$response = wp_remote_get( $base_url . $path, array(
			'timeout' => 15,
			'headers' => array(
				'X-Algolia-Application-Id' => $app_id,
				'X-Algolia-API-Key'        => $admin_key,
				'Accept'                   => 'application/json',
				'User-Agent'               => 'ZymargAlgolia/' . ZYMARG_ALGOLIA_VERSION,
			),
		) );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	/**
	 * Configuration health check (1.0.16+). Pure local — no API calls.
	 * Returns an array of { level: ok|warn|error, label: string }.
	 *
	 * @param Zymarg_Algolia_Client $client Client.
	 * @return array
	 */
	protected function get_health_checks( $client ) {
		$checks = array();

		$app_id     = zymarg_algolia_get_setting( 'app_id' );
		$admin_key  = zymarg_algolia_get_setting( 'admin_api_key' );
		$search_key = zymarg_algolia_get_setting( 'search_api_key' );

		$checks[] = array(
			'level' => $app_id ? 'ok' : 'error',
			'label' => $app_id ? 'Search Engine App ID configured' : 'Search Engine App ID is missing',
		);
		$checks[] = array(
			'level' => $admin_key ? 'ok' : 'error',
			'label' => $admin_key ? 'Admin API Key configured' : 'Admin API Key is missing',
		);
		$checks[] = array(
			'level' => $search_key ? 'ok' : 'error',
			'label' => $search_key ? 'Search-Only API Key configured' : 'Search-Only API Key is missing',
		);

		$stats = $this->get_index_stats( $client );
		foreach ( $stats as $name => $count ) {
			$short = ucfirst( str_replace( zymarg_algolia_get_setting( 'index_prefix', 'zymarg_' ), '', $name ) );
			if ( $count > 0 ) {
				$checks[] = array( 'level' => 'ok', 'label' => $short . ' index has ' . number_format_i18n( $count ) . ' records' );
			} else {
				$checks[] = array( 'level' => 'warn', 'label' => $short . ' index is empty (run "Reindex everything")' );
			}
		}

		$cta_mode = zymarg_algolia_get_setting( 'cta_mode', 'dropdown' );
		$cta_label = $cta_mode === 'search_page'
			? 'CTA mode: banner on search results page'
			: ( $cta_mode === 'hidden' ? 'CTA mode: hidden everywhere' : 'CTA mode: in-dropdown (default)' );
		$checks[] = array( 'level' => 'ok', 'label' => $cta_label );

		$region = zymarg_algolia_get_setting( 'analytics_region', 'auto' );
		$reg_label = $region === 'auto' ? 'auto-detect' : ( $region === 'eu' ? 'EU forced' : 'Global forced' );
		$checks[] = array( 'level' => 'ok', 'label' => 'Analytics region: ' . $reg_label );

		return $checks;
	}

	/**
	 * Tiny inline-SVG sparkline. No charting library, zero deps.
	 *
	 * @param array $points  Numeric values (one per day).
	 * @param int   $w       SVG width.
	 * @param int   $h       SVG height.
	 * @return string
	 */
	protected function render_sparkline( $points, $w = 110, $h = 28 ) {
		$points = array_values( array_map( 'floatval', $points ) );
		if ( count( $points ) < 2 ) {
			return '';
		}
		$max = max( $points );
		$min = min( $points );
		if ( $max == $min ) {
			$max = $min + 1;
		}
		$step = ( count( $points ) > 1 ) ? ( $w / ( count( $points ) - 1 ) ) : $w;
		$d = '';
		foreach ( $points as $i => $v ) {
			$x = $i * $step;
			$y = $h - ( ( $v - $min ) / ( $max - $min ) ) * ( $h - 2 ) - 1;
			$d .= ( $i === 0 ? 'M' : 'L' ) . round( $x, 1 ) . ',' . round( $y, 1 ) . ' ';
		}
		return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" style="display:block;margin-top:4px;">' .
			'<path d="' . esc_attr( $d ) . '" fill="none" stroke="#7B3FE4" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" />' .
		'</svg>';
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
