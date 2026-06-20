<?php
/**
 * No-results logger.
 *
 * Captures search queries that returned zero results, sent from the frontend
 * via admin-ajax. Stored locally in a WP option so the data is available on
 * the Algolia free tier without depending on Algolia Analytics retention.
 *
 * Only active when the "No-results logging" feature toggle is ON.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Logger
 */
class Zymarg_Algolia_Logger {

	/** Option that stores captured zero-result queries. */
	const OPTION = 'zymarg_algolia_local_no_results';

	/** Max distinct queries to keep (oldest pruned first). */
	const MAX_ENTRIES = 200;

	/** Max length of a stored query string. */
	const MAX_QUERY_LEN = 120;

	public function __construct() {
		add_action( 'wp_ajax_zymarg_algolia_log_no_results', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_zymarg_algolia_log_no_results', array( $this, 'handle' ) );
	}

	/**
	 * Handle an incoming no-results report from the frontend.
	 */
	public function handle() {
		// Feature must be enabled.
		if ( empty( zymarg_algolia_get_setting( 'feat_no_results_log' ) ) ) {
			wp_send_json_error( 'disabled', 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zymarg_algolia_log_no_results' ) ) {
			wp_send_json_error( 'bad_nonce', 403 );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$query = trim( $query );
		if ( '' === $query ) {
			wp_send_json_error( 'empty', 400 );
		}
		if ( function_exists( 'mb_substr' ) ) {
			$query = mb_substr( $query, 0, self::MAX_QUERY_LEN );
		} else {
			$query = substr( $query, 0, self::MAX_QUERY_LEN );
		}

		self::record( $query );
		wp_send_json_success();
	}

	/**
	 * Record (or increment) a zero-result query.
	 *
	 * @param string $query Sanitized query.
	 */
	public static function record( $query ) {
		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$key = strtolower( $query );

		if ( isset( $log[ $key ] ) ) {
			$log[ $key ]['c'] = (int) $log[ $key ]['c'] + 1;
			$log[ $key ]['t'] = time();
		} else {
			$log[ $key ] = array(
				'q' => $query,
				'c' => 1,
				't' => time(),
			);
		}

		// Prune: keep the most recently seen entries only.
		if ( count( $log ) > self::MAX_ENTRIES ) {
			uasort(
				$log,
				function ( $a, $b ) {
					return (int) $b['t'] <=> (int) $a['t'];
				}
			);
			$log = array_slice( $log, 0, self::MAX_ENTRIES, true );
		}

		update_option( self::OPTION, $log, false );
	}

	/**
	 * Get captured queries sorted by frequency (desc), then recency.
	 *
	 * @param int $limit Max rows.
	 * @return array List of array{q:string,c:int,t:int}.
	 */
	public static function get_top( $limit = 20 ) {
		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) || empty( $log ) ) {
			return array();
		}
		$rows = array_values( $log );
		usort(
			$rows,
			function ( $a, $b ) {
				if ( (int) $a['c'] === (int) $b['c'] ) {
					return (int) $b['t'] <=> (int) $a['t'];
				}
				return (int) $b['c'] <=> (int) $a['c'];
			}
		);
		return array_slice( $rows, 0, (int) $limit );
	}

	/**
	 * Clear all captured queries.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
