<?php
/**
 * Lightweight Algolia REST client using wp_remote_request.
 * No Composer / no autoload needed. Safe on shared hosts (Hostinger).
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
	 * Constructor.
	 *
	 * @param string|null $app_id    Optional override.
	 * @param string|null $admin_key Optional override.
	 */
	public function __construct( $app_id = null, $admin_key = null ) {
		$this->app_id    = null !== $app_id ? $app_id : zymarg_algolia_get_setting( 'app_id' );
		$this->admin_key = null !== $admin_key ? $admin_key : zymarg_algolia_get_setting( 'admin_api_key' );
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
	 * Build the base host (rotates on read).
	 *
	 * @param bool $write True for write host.
	 * @return string
	 */
	protected function host( $write = false ) {
		if ( $write ) {
			return 'https://' . $this->app_id . '.algolia.net';
		}
		return 'https://' . $this->app_id . '-dsn.algolia.net';
	}

	/**
	 * Perform an HTTP request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   API path (starts with /).
	 * @param array  $body   Body to send (will be JSON-encoded).
	 * @param bool   $write  True if write op (different host pool).
	 * @return array|WP_Error Decoded response or error.
	 */
	protected function request( $method, $path, $body = array(), $write = true ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'zymarg_algolia_not_configured', 'Algolia credentials missing.' );
		}

		$url = $this->host( $write ) . $path;

		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'X-Algolia-Application-Id' => $this->app_id,
				'X-Algolia-API-Key'        => $this->admin_key,
				'Content-Type'             => 'application/json; charset=utf-8',
				'User-Agent'               => 'ZymargAlgolia/' . ZYMARG_ALGOLIA_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
			),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'Algolia HTTP ' . $code;
			return new WP_Error( 'zymarg_algolia_http_' . $code, $message, $data );
		}

		return is_array( $data ) ? $data : array();
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
