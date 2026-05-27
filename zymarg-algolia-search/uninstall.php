<?php
/**
 * Uninstall: remove plugin options.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'zymarg_algolia_settings' );
delete_option( 'zymarg_algolia_setup_complete' );
