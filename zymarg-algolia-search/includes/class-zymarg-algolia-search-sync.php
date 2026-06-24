<?php
/**
 * Cross-device recent-search sync via user_meta (1.0.36).
 *
 * Provides two AJAX endpoints (logged-in only):
 *   - zymarg_get_searches  → returns the stored list for the current user.
 *   - zymarg_push_searches → merges a new term into the stored list.
 *
 * Guest users (not logged in) are silently ignored — the JS falls back
 * to localStorage automatically.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zymarg_Algolia_Search_Sync {

	const META_KEY = '_zymarg_recent_searches';
	const LIMIT    = 5;
	const NONCE    = 'zymarg_search_sync';

	public function __construct() {
		add_action( 'wp_ajax_zymarg_get_searches',  array( $this, 'handle_get' ) );
		add_action( 'wp_ajax_zymarg_push_searches', array( $this, 'handle_push' ) );
	}

	/**
	 * GET — return the current user's saved recent searches.
	 */
	public function handle_get() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$list = $this->get_list( get_current_user_id() );
		wp_send_json_success( array( 'searches' => $list ) );
	}

	/**
	 * PUSH — add a new term to the top of the list, de-duplicate, cap at LIMIT.
	 */
	public function handle_push() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		if ( '' === $term ) {
			wp_send_json_error( 'empty_term' );
		}

		$uid  = get_current_user_id();
		$list = $this->get_list( $uid );

		// Remove existing occurrence (case-insensitive) then prepend.
		$list = array_values( array_filter( $list, function ( $s ) use ( $term ) {
			return strtolower( $s ) !== strtolower( $term );
		} ) );
		array_unshift( $list, $term );
		$list = array_slice( $list, 0, self::LIMIT );

		update_user_meta( $uid, self::META_KEY, $list );
		wp_send_json_success( array( 'searches' => $list ) );
	}

	/**
	 * Read saved list for a user, always returns a plain array of strings.
	 */
	private function get_list( $uid ) {
		$raw = get_user_meta( $uid, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $raw ) ) );
	}
}
