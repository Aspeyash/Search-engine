<?php
/**
 * Admin settings page (Settings -> ZYMARG Algolia).
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Settings
 */
class Zymarg_Algolia_Settings {

	const OPTION = 'zymarg_algolia_settings';
	const SLUG   = 'zymarg-algolia';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_zymarg_algolia_reindex', array( $this, 'handle_reindex' ) );
		add_action( 'admin_post_zymarg_algolia_verify', array( $this, 'handle_verify' ) );
	}

	public function menu() {
		add_options_page(
			'ZYMARG Algolia Search',
			'ZYMARG Algolia',
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function register() {
		register_setting(
			'zymarg_algolia_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => zymarg_algolia_default_settings(),
			)
		);
	}

	/**
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = zymarg_algolia_default_settings();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		$out['app_id']          = isset( $input['app_id'] ) ? sanitize_text_field( $input['app_id'] ) : '';
		$out['admin_api_key']   = isset( $input['admin_api_key'] ) ? sanitize_text_field( $input['admin_api_key'] ) : '';
		$out['search_api_key']  = isset( $input['search_api_key'] ) ? sanitize_text_field( $input['search_api_key'] ) : '';
		$out['index_prefix']    = isset( $input['index_prefix'] ) ? sanitize_key( $input['index_prefix'] ) : 'zymarg_';
		$out['community_url']   = isset( $input['community_url'] ) ? esc_url_raw( $input['community_url'] ) : home_url( '/community' );
		$out['no_results_text'] = isset( $input['no_results_text'] ) ? sanitize_text_field( $input['no_results_text'] ) : "Couldn't find what you're looking for?";
		$out['request_btn']     = isset( $input['request_btn'] ) ? sanitize_text_field( $input['request_btn'] ) : 'Request Here';
		$out['auto_index']      = ! empty( $input['auto_index'] ) ? 1 : 0;
		$out['enable_in_admin'] = ! empty( $input['enable_in_admin'] ) ? 1 : 0;

		// Languages.
		$langs = isset( $input['languages'] ) ? (array) $input['languages'] : array( 'en', 'bn' );
		$langs = array_filter( array_map( 'sanitize_key', $langs ) );
		$out['languages'] = ! empty( $langs ) ? array_values( array_unique( $langs ) ) : array( 'en', 'bn' );

		return $out;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			zymarg_algolia_default_settings()
		);

		$reindex_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=zymarg_algolia_reindex' ),
			'zymarg_algolia_reindex'
		);
		$verify_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=zymarg_algolia_verify' ),
			'zymarg_algolia_verify'
		);

		$notice = isset( $_GET['zymarg_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['zymarg_notice'] ) ) : '';
		$msg    = isset( $_GET['zymarg_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['zymarg_msg'] ) ) : '';
		?>
		<div class="wrap">
			<h1>ZYMARG Algolia Search</h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice ); ?> is-dismissible">
					<p><?php echo esc_html( $msg ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				Configure your Algolia credentials, then run a full reindex. Indexing happens
				automatically in the background whenever products, categories, or vendors are added,
				updated, or removed.
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'zymarg_algolia_group' ); ?>

				<h2 class="title">Algolia credentials</h2>
				<p class="description">
					Get these from your Algolia dashboard: API Keys section.
					Application ID + Admin API Key (write) + Search-Only API Key (public, used in frontend).
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="zymarg_app_id">Application ID</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[app_id]" type="text"
								id="zymarg_app_id" value="<?php echo esc_attr( $settings['app_id'] ); ?>"
								class="regular-text" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_admin_key">Admin API Key</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[admin_api_key]" type="password"
								id="zymarg_admin_key" value="<?php echo esc_attr( $settings['admin_api_key'] ); ?>"
								class="regular-text" autocomplete="off" />
							<p class="description">Write access. Used server-side only. Never exposed to the browser.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_search_key">Search-Only API Key</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[search_api_key]" type="text"
								id="zymarg_search_key" value="<?php echo esc_attr( $settings['search_api_key'] ); ?>"
								class="regular-text" autocomplete="off" />
							<p class="description">Public key used in the frontend instant search.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_index_prefix">Index prefix</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[index_prefix]" type="text"
								id="zymarg_index_prefix" value="<?php echo esc_attr( $settings['index_prefix'] ); ?>"
								class="regular-text" />
							<p class="description">Indices created: <code><?php echo esc_html( $settings['index_prefix'] ); ?>products</code>, <code><?php echo esc_html( $settings['index_prefix'] ); ?>vendors</code>, <code><?php echo esc_html( $settings['index_prefix'] ); ?>categories</code>.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Search behavior</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Languages</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[languages][]" value="en"
									<?php checked( in_array( 'en', (array) $settings['languages'], true ) ); ?> /> English
							</label>
							&nbsp;&nbsp;
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[languages][]" value="bn"
									<?php checked( in_array( 'bn', (array) $settings['languages'], true ) ); ?> /> Bengali (Bangla)
							</label>
							<p class="description">Both are recommended for ZYMARG (Bangladesh marketplace).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Auto-index on save</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_index]" value="1"
									<?php checked( ! empty( $settings['auto_index'] ) ); ?> /> Re-index when products/vendors/categories change
							</label>
						</td>
					</tr>
				</table>

				<h2 class="title">No-results CTA</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="zymarg_no_results_text">Message</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[no_results_text]" type="text"
								id="zymarg_no_results_text"
								value="<?php echo esc_attr( $settings['no_results_text'] ); ?>"
								class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_request_btn">Button label</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[request_btn]" type="text"
								id="zymarg_request_btn"
								value="<?php echo esc_attr( $settings['request_btn'] ); ?>"
								class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_community_url">Community Request Board URL</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[community_url]" type="url"
								id="zymarg_community_url"
								value="<?php echo esc_attr( $settings['community_url'] ); ?>"
								class="regular-text" />
							<p class="description">Default: <code><?php echo esc_html( home_url( '/community' ) ); ?></code></p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Save settings' ); ?>
			</form>

			<hr />

			<h2 class="title">Index actions</h2>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $verify_url ); ?>">Verify connection</a>
				&nbsp;
				<a class="button button-primary" href="<?php echo esc_url( $reindex_url ); ?>"
					onclick="return confirm('Reindex everything now? This runs in the background.');">
					Reindex everything now
				</a>
			</p>

			<h2 class="title">How to use</h2>
			<ol>
				<li>Save credentials above.</li>
				<li>Click <em>Verify connection</em>, then <em>Reindex everything now</em>.</li>
				<li>Add the search bar to your header in Elementor with the shortcode
					<code>[zymarg_algolia_search]</code> or place the widget block.</li>
				<li>It also auto-replaces the WooCommerce <code>[wcsearch]</code>-style search if you
					prefer to drop the shortcode into your Astra header.</li>
			</ol>
		</div>
		<?php
	}

	public function handle_verify() {
		check_admin_referer( 'zymarg_algolia_verify' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		$client = new Zymarg_Algolia_Client();
		$result = $client->verify();
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', 'Algolia connection failed: ' . $result->get_error_message() );
		}
		$this->redirect_with_notice( 'success', 'Algolia connection OK. Found ' . ( isset( $result['items'] ) ? count( $result['items'] ) : 0 ) . ' indices.' );
	}

	public function handle_reindex() {
		check_admin_referer( 'zymarg_algolia_reindex' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		$products   = new Zymarg_Algolia_Products();
		$vendors    = new Zymarg_Algolia_Vendors();
		$categories = new Zymarg_Algolia_Categories();

		$total  = 0;
		$total += $products->reindex_all();
		$total += $vendors->reindex_all();
		$total += $categories->reindex_all();

		$this->redirect_with_notice( 'success', sprintf( 'Queued %d records for reindex. Indexing runs in the background.', $total ) );
	}

	protected function redirect_with_notice( $type, $msg ) {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		$url = add_query_arg(
			array(
				'zymarg_notice' => $type,
				'zymarg_msg'    => rawurlencode( $msg ),
			),
			$url
		);
		wp_safe_redirect( $url );
		exit;
	}
}
