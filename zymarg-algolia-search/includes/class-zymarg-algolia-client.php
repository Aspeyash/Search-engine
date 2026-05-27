<?php
/**
 * Lightweight Algolia REST client using wp_remote_request.
 * No Composer / no autoload needed. Safe on shared hosts (Hostinger).
 *
 * Implements Algolia's official failover host strategy:
 *   - Read pool:  {appid}-dsn.algolia.net, then {appid}-1/2/3.algolianet.com
 *   - Write pool: {appid}.algolia.net,    then {appid}-1/2/3.algolianet.com
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Client
 */
class Zymarg_Algolia_Client {

	/**
	 * Algolia application ID.
	 *
	 * @var string
	 */
	protected $app_id;

	/**
	 * Algolia admin API key (write access).
	 *
	 * @var string
	 */
	protected $admin_key;

	/**
	 * Last raw HTTP details (for diagnostics).
	 *
	 * @var array
	 */
	protected $last_debug = array();

	/**
	 * Constructor.
	 *
	 * @param string|null $app_id    Optional override.
	 * @param string|null $admin_key Optional override.
	 */
	public function __construct( $app_id = null, $admin_key = null ) {
		$this->app_id    = null !== $app_id ? trim( (string) $app_id ) : trim( (string) zymarg_algolia_get_setting( 'app_id' ) );
		$this->admin_key = null !== $admin_key ? trim( (string) $admin_key ) : trim( (string) zymarg_algolia_get_setting( 'admin_api_key' ) );
	}

	/**
	 * Are credentials configured?
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->app_id ) && ! empty( $this->admin_key );
	}

	/**
	 * Get debug info from the last failed request.
	 *
	 * @return array
	 */
	public function get_last_debug() {
		return $this->last_debug;
	}

	/**
	 * Build the host pool for a given operation.
	 * Matches the official Algolia SDK behavior (primary + 3 failovers).
	 *
	 * @param bool $write True for write hosts.
	 * @return array
	 */
	protected function get_hosts( $write = false ) {
		$id = $this->app_id;

		// Failover hosts (shuffled to spread load, like the official client).
		$failover = array(
			'https://' . $id . '-1.algolianet.com',
			'https://' . $id . '-2.algolianet.com',
			'https://' . $id . '-3.algolianet.com',
		);
		shuffle( $failover );

		if ( $write ) {
			return array_merge( array( 'https://' . $id . '.algolia.net' ), $failover );
		}
		return array_merge( array( 'https://' . $id . '-dsn.algolia.net' ), $failover );
	}

	/**
	 * Perform an HTTP request, with host failover.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   API path (starts with /).
	 * @param array  $body   Body to send (will be JSON-encoded).
	 * @param bool   $write  True if write op.
	 * @return array|WP_Error Decoded response or error.
	 */
	protected function request( $method, $path, $body = array(), $write = true ) {
		$this->last_debug = array();

		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'zymarg_algolia_not_configured',
				'Algolia credentials are missing or empty. Make sure you have saved both the Application ID and the Admin API Key on the settings page before clicking Verify.'
			);
		}

		$method = strtoupper( $method );
		$is_get = ( 'GET' === $method );

		$hosts      = $this->get_hosts( $write );
		$last_error = null;

		foreach ( $hosts as $host ) {
			$url  = $host . $path;
			$args = array(
				'method'    => $method,
				'timeout'   => $is_get ? 15 : 30,
				'sslverify' => true,
				'headers'   => array(
					'X-Algolia-Application-Id' => $this->app_id,
					'X-Algolia-API-Key'        => $this->admin_key,
					'Accept'                   => 'application/json',
					'User-Agent'               => 'ZymargAlgolia/' . ZYMARG_ALGOLIA_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
			);

			// IMPORTANT: only attach Content-Type and body for write methods.
			// Sending Content-Type on GET trips up some Hostinger / cPanel WAFs.
			if ( ! $is_get && ! empty( $body ) ) {
				$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
				$args['body']                    = wp_json_encode( $body );
			}

			$response = wp_remote_request( $url, $args );

			// Network/SSL/DNS error -> try next host.
			if ( is_wp_error( $response ) ) {
				$last_error = new WP_Error(
					'zymarg_algolia_network',
					sprintf(
						'Network error contacting Algolia (%s): %s',
						$host,
						$response->get_error_message()
					)
				);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$raw  = (string) wp_remote_retrieve_body( $response );
			$data = json_decode( $raw, true );

			$this->last_debug = array(
				'url'    => $url,
				'method' => $method,
				'code'   => $code,
				'body'   => substr( $raw, 0, 500 ),
			);

			// 5xx and 0 are retryable -> next host.
			if ( $code === 0 || $code >= 500 ) {
				$last_error = new WP_Error(
					'zymarg_algolia_http_' . $code,
					sprintf( 'Algolia returned HTTP %d from %s. Trying next host…', $code, $host )
				);
				continue;
			}

			// 4xx: client error -> stop, return real Algolia message.
			if ( $code >= 400 ) {
				$msg  = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $code );
				$hint = $this->error_hint( $code, $msg );
				return new WP_Error(
					'zymarg_algolia_http_' . $code,
					sprintf( 'Algolia HTTP %d: %s%s', $code, $msg, $hint ),
					array(
						'data' => $data,
						'url'  => $url,
					)
				);
			}

			// 2xx -> success.
			return is_array( $data ) ? $data : array();
		}

		// All hosts failed.
		return $last_error
			? $last_error
			: new WP_Error( 'zymarg_algolia_unknown', 'All Algolia hosts failed unexpectedly.' );
	}

	/**
	 * Human-readable hint based on HTTP status / Algolia message.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $msg  Algolia message.
	 * @return string
	 */
	protected function error_hint( $code, $msg ) {
		$lower = strtolower( $msg );
		if ( 401 === $code || 403 === $code || strpos( $lower, 'invalid' ) !== false || strpos( $lower, 'application' ) !== false ) {
			return ' — Check that the Application ID is correct and the Admin API Key has the right ACL (listIndexes, addObject, settings).';
		}
		if ( 404 === $code ) {
			return ' — Endpoint not found. This usually means the Application ID is wrong or your Algolia plan does not include this endpoint.';
		}
		if ( 429 === $code ) {
			return ' — Too many requests. Wait a moment and try again.';
		}
		return '';
	}

	/* ---------------------------------------------------------------------- */
	/* High-level operations.                                                  */
	/* ---------------------------------------------------------------------- */

	/**
	 * Save (replace) one record. The record must contain "objectID".
	 *
	 * @param string $index_name Index name.
	 * @param array  $object     Record.
	 * @return array|WP_Error
	 */
	public function save_object( $index_name, $object ) {
		if ( empty( $object['objectID'] ) ) {
			return new WP_Error( 'zymarg_algolia_missing_objectid', 'objectID required.' );
		}
		$path = '/1/indexes/' . rawurlencode( $index_name ) . '/' . rawurlencode( $object['objectID'] );
		return $this->request( 'PUT', $path, $object );
	}

	/**
	 * Save many records using batch endpoint.
	 *
	 * @param string $index_name Index.
	 * @param array  $objects    List of records (each with objectID).
	 * @return array|WP_Error
	 */
	public function save_objects( $index_name, $objects ) {
		if ( empty( $objects ) ) {
			return array( 'taskID' => 0 );
		}
		$requests = array();
		foreach ( $objects as $obj ) {
			if ( empty( $obj['objectID'] ) ) {
				continue;
			}
			$requests[] = array(
				'action' => 'updateObject',
				'body'   => $obj,
			);
		}
		$path = '/1/indexes/' . rawurlencode( $index_name ) . '/batch';
		return $this->request( 'POST', $path, array( 'requests' => $requests ) );
	}

	/**
	 * Delete an object by ID.
	 *
	 * @param string $index_name Index.
	 * @param string $object_id  Object ID.
	 * @return array|WP_Error
	 */
	public function delete_object( $index_name, $object_id ) {
		$path = '/1/indexes/' . rawurlencode( $index_name ) . '/' . rawurlencode( $object_id );
		return $this->request( 'DELETE', $path );
	}

	/**
	 * Clear an index (remove all records but keep settings).
	 *
	 * @param string $index_name Index.
	 * @return array|WP_Error
	 */
	public function clear_index( $index_name ) {
		$path = '/1/indexes/' . rawurlencode( $index_name ) . '/clear';
		return $this->request( 'POST', $path );
	}

	/**
	 * Set index settings (typo tolerance, ranking, languages, etc).
	 *
	 * @param string $index_name Index.
	 * @param array  $settings   Algolia settings payload.
	 * @return array|WP_Error
	 */
	public function set_settings( $index_name, $settings ) {
		$path = '/1/indexes/' . rawurlencode( $index_name ) . '/settings';
		return $this->request( 'PUT', $path, $settings );
	}

	/**
	 * Verify credentials by listing indices.
	 *
	 * @return array|WP_Error
	 */
	public function verify() {
		return $this->request( 'GET', '/1/indexes', array(), false );
	}
}
