<?php
/**
 * Plugin Name:       ZYMARG Algolia Search
 * Plugin URI:        https://github.com/Aspeyash/Search-engine-
 * Description:       Algolia-powered instant search for the ZYMARG marketplace. Indexes WooCommerce products, product categories, and Dokan vendors. Renders a brand-styled instant search dropdown with a custom "no results" CTA that links to the Community Request Board.
 * Version:           1.0.3
 * Author:            ZYMARG
 * Author URI:        https://zymarg.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zymarg-algolia
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/Aspeyash/Search-engine-
 * GitHub Plugin URI: Aspeyash/Search-engine-
 * Primary Branch:    main
 * WC requires at least: 6.0
 * WC tested up to:   9.0
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZYMARG_ALGOLIA_VERSION', '1.0.3' );
define( 'ZYMARG_ALGOLIA_FILE', __FILE__ );
define( 'ZYMARG_ALGOLIA_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZYMARG_ALGOLIA_URL', plugin_dir_url( __FILE__ ) );
define( 'ZYMARG_ALGOLIA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Default plugin settings.
 */
function zymarg_algolia_default_settings() {
	return array(
		'app_id'          => '',
		'admin_api_key'   => '',
		'search_api_key'  => '',
		'index_prefix'    => 'zymarg_',
		'community_url'   => home_url( '/community' ),
		'languages'       => array( 'en', 'bn' ),
		'auto_index'      => 1,
		'enable_in_admin' => 1,
		'no_results_text' => "Couldn't find what you're looking for?",
		'request_btn'     => 'Request Here',
	);
}

/**
 * Get a single plugin setting (with sensible defaults).
 */
function zymarg_algolia_get_setting( $key, $default = null ) {
	$settings = wp_parse_args(
		(array) get_option( 'zymarg_algolia_settings', array() ),
		zymarg_algolia_default_settings()
	);
	if ( null === $default ) {
		$defaults = zymarg_algolia_default_settings();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default;
}

/**
 * Get full index name with prefix.
 *
 * @param string $type One of: products, vendors, categories.
 * @return string
 */
function zymarg_algolia_index_name( $type ) {
	$prefix = zymarg_algolia_get_setting( 'index_prefix', 'zymarg_' );
	return $prefix . $type;
}

// Load core classes.
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-client.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-indexer.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-products.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-vendors.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-categories.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-settings.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-frontend.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-shortcode.php';
require_once ZYMARG_ALGOLIA_PATH . 'includes/class-zymarg-algolia-updater.php';

/**
 * Declare compatibility with WooCommerce features (HPOS + Blocks).
 * This removes the "incompatible plugins" warning.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			__FILE__,
			true
		);
	}
} );

/**
 * Boot the plugin.
 */
function zymarg_algolia_boot() {
	// Admin only.
	if ( is_admin() ) {
		new Zymarg_Algolia_Settings();
	}

	// Indexers (run on all requests so save_post hooks fire).
	new Zymarg_Algolia_Products();
	new Zymarg_Algolia_Vendors();
	new Zymarg_Algolia_Categories();

	// Frontend.
	new Zymarg_Algolia_Frontend();
	new Zymarg_Algolia_Shortcode();

	// GitHub auto-updater (admin only).
	if ( is_admin() ) {
		new Zymarg_Algolia_Updater(
			ZYMARG_ALGOLIA_FILE,
			array(
				'owner'  => 'Aspeyash',
				'repo'   => 'Search-engine-',
				'branch' => 'main',
			)
		);
	}
}
add_action( 'plugins_loaded', 'zymarg_algolia_boot', 20 );

/**
 * Activation: seed default settings, schedule first reindex.
 */
function zymarg_algolia_activate() {
	if ( ! get_option( 'zymarg_algolia_settings' ) ) {
		add_option( 'zymarg_algolia_settings', zymarg_algolia_default_settings() );
	}
	// Mark that the user still needs to add credentials.
	if ( ! get_option( 'zymarg_algolia_setup_complete' ) ) {
		add_option( 'zymarg_algolia_setup_complete', 0 );
	}
}
register_activation_hook( __FILE__, 'zymarg_algolia_activate' );

/**
 * Deactivation: clear scheduled events.
 */
function zymarg_algolia_deactivate() {
	wp_clear_scheduled_hook( 'zymarg_algolia_reindex_batch' );
}
register_deactivation_hook( __FILE__, 'zymarg_algolia_deactivate' );

/**
 * Show admin notice until credentials are added.
 */
function zymarg_algolia_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$app_id = zymarg_algolia_get_setting( 'app_id' );
	if ( empty( $app_id ) ) {
		$url = admin_url( 'options-general.php?page=zymarg-algolia' );
		echo '<div class="notice notice-warning"><p><strong>ZYMARG Algolia Search:</strong> ';
		echo esc_html__( 'Add your Algolia credentials to enable search. ', 'zymarg-algolia' );
		echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open settings', 'zymarg-algolia' ) . '</a>';
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'zymarg_algolia_admin_notice' );
