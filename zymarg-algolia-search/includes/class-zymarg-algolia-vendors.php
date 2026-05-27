<?php
/**
 * Dokan vendor indexer.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Vendors
 */
class Zymarg_Algolia_Vendors extends Zymarg_Algolia_Indexer {

	/**
	 * @return string
	 */
	protected function slug() {
		return 'vendors';
	}

	/**
	 * @return string
	 */
	public function get_index_name() {
		return zymarg_algolia_index_name( 'vendors' );
	}

	/**
	 * Hook into Dokan + WordPress events that change vendor info.
	 */
	protected function register_hooks() {
		// Dokan-specific hooks.
		add_action( 'dokan_new_seller_created', array( $this, 'on_vendor_change' ), 10, 1 );
		add_action( 'dokan_store_profile_saved', array( $this, 'on_vendor_change' ), 10, 1 );
		add_action( 'dokan_settings_updated', array( $this, 'on_vendor_change' ), 10, 1 );
		add_action( 'dokan_seller_register_method', array( $this, 'on_vendor_change' ), 10, 1 );

		// Generic profile changes.
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 10, 1 );

		// Removal.
		add_action( 'delete_user', array( $this, 'on_user_delete' ), 10, 1 );

		// Approval state changes.
		add_action( 'dokan_vendor_enabled', array( $this, 'on_vendor_change' ), 10, 1 );
		add_action( 'dokan_vendor_disabled', array( $this, 'on_vendor_change' ), 10, 1 );
	}

	public function on_vendor_change( $user_id ) {
		if ( $user_id ) {
			$this->queue_one( (int) $user_id );
		}
	}

	public function on_profile_update( $user_id ) {
		if ( $this->is_vendor( $user_id ) ) {
			$this->queue_one( (int) $user_id );
		}
	}

	public function on_user_delete( $user_id ) {
		$this->delete_one( $user_id );
	}

	/**
	 * Is this user a Dokan vendor?
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function is_vendor( $user_id ) {
		if ( function_exists( 'dokan_is_user_seller' ) ) {
			return (bool) dokan_is_user_seller( $user_id );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return in_array( 'seller', (array) $user->roles, true ) || in_array( 'vendor', (array) $user->roles, true );
	}

	/**
	 * Build vendor record.
	 *
	 * @param int $user_id Vendor user ID.
	 * @return array|null
	 */
	public function build_record( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! $this->is_vendor( $user_id ) ) {
			return null;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$shop_name   = '';
		$shop_url    = '';
		$avatar      = '';
		$banner      = '';
		$description = '';
		$address     = array();
		$social      = array();
		$enabled     = true;
		$rating      = 0;
		$review_cnt  = 0;
		$prod_count  = 0;

		if ( function_exists( 'dokan_get_vendor' ) ) {
			$vendor = dokan_get_vendor( $user_id );
			if ( $vendor ) {
				$shop_name   = (string) $vendor->get_shop_name();
				$shop_url    = (string) $vendor->get_shop_url();
				$avatar      = (string) $vendor->get_avatar();
				$banner      = method_exists( $vendor, 'get_banner' ) ? (string) $vendor->get_banner() : '';
				$description = method_exists( $vendor, 'get_shop_description' ) ? (string) $vendor->get_shop_description() : '';
				$address     = method_exists( $vendor, 'get_address' ) ? (array) $vendor->get_address() : array();
				$social      = method_exists( $vendor, 'get_social' ) ? (array) $vendor->get_social() : array();
				$enabled     = method_exists( $vendor, 'is_enabled' ) ? (bool) $vendor->is_enabled() : true;
				if ( method_exists( $vendor, 'get_rating' ) ) {
					$r          = $vendor->get_rating();
					$rating     = isset( $r['rating'] ) ? (float) $r['rating'] : 0;
					$review_cnt = isset( $r['count'] ) ? (int) $r['count'] : 0;
				}
			}
		}

		if ( ! $shop_name ) {
			$shop_name = $user->display_name ? $user->display_name : $user->user_login;
		}

		// Product count.
		$prod_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'author'         => $user_id,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		);
		$prod_count = (int) $prod_query->found_posts;

		// Skip disabled / empty vendors.
		if ( ! $enabled ) {
			return null;
		}

		$record = array(
			'objectID'      => $this->object_id( $user_id ),
			'id'            => $user_id,
			'type'          => 'vendor',
			'name'          => $shop_name,
			'display_name'  => $user->display_name,
			'permalink'     => $shop_url,
			'avatar'        => $avatar,
			'banner'        => $banner,
			'description'   => wp_strip_all_tags( $description ),
			'address'       => $address,
			'social'        => $social,
			'rating'        => $rating,
			'review_count'  => $review_cnt,
			'product_count' => $prod_count,
			'date_created'  => strtotime( $user->user_registered ),
		);

		/**
		 * Filter vendor record before pushing to Algolia.
		 */
		return apply_filters( 'zymarg_algolia_vendor_record', $record, $user );
	}

	/**
	 * @return array
	 */
	public function get_all_ids() {
		$args = array(
			'role__in' => array( 'seller', 'vendor' ),
			'fields'   => 'ID',
			'number'   => -1,
		);
		$users = get_users( $args );
		return array_map( 'intval', $users );
	}

	/**
	 * @return array
	 */
	public function get_index_settings() {
		$languages = (array) zymarg_algolia_get_setting( 'languages', array( 'en', 'bn' ) );
		return array(
			'searchableAttributes'  => array(
				'unordered(name)',
				'unordered(display_name)',
				'unordered(description)',
			),
			'attributesForFaceting' => array( 'searchable(name)' ),
			'customRanking'         => array(
				'desc(product_count)',
				'desc(rating)',
				'desc(review_count)',
			),
			'queryLanguages'        => $languages,
			'indexLanguages'        => $languages,
			'removeStopWords'       => $languages,
			'ignorePlurals'         => $languages,
			'typoTolerance'         => true,
			'highlightPreTag'       => '<mark>',
			'highlightPostTag'      => '</mark>',
		);
	}
}
