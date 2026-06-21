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
		add_action( 'admin_post_zymarg_algolia_cleanup', array( $this, 'handle_cleanup' ) );
		add_action( 'wp_ajax_zymarg_algolia_save_notes', array( $this, 'ajax_save_notes' ) );
	}

	public function menu() {
		add_menu_page(
			'Search Engine',
			'Search Engine',
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-search',
			58
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
		$out['community_url']     = isset( $input['community_url'] ) ? esc_url_raw( $input['community_url'] ) : home_url( '/community' );
		$out['no_results_text']   = isset( $input['no_results_text'] ) ? sanitize_text_field( $input['no_results_text'] ) : "Couldn't find what you're looking for?";
		$out['request_btn']       = isset( $input['request_btn'] ) ? sanitize_text_field( $input['request_btn'] ) : 'Request Here';
		$out['trending_fallback'] = isset( $input['trending_fallback'] ) ? sanitize_text_field( $input['trending_fallback'] ) : '';
		$out['show_trending']     = ! empty( $input['show_trending'] ) ? 1 : 0;
		$out['auto_index']      = ! empty( $input['auto_index'] ) ? 1 : 0;
		$out['enable_in_admin'] = ! empty( $input['enable_in_admin'] ) ? 1 : 0;

		// Smart feature on/off switches (2.0.0).
		$out['feat_recent']       = ! empty( $input['feat_recent'] ) ? 1 : 0;
		$out['feat_keyboard']     = ! empty( $input['feat_keyboard'] ) ? 1 : 0;
		$out['feat_insights']     = ! empty( $input['feat_insights'] ) ? 1 : 0;
		$out['feat_related']      = ! empty( $input['feat_related'] ) ? 1 : 0;
		$out['feat_result_count'] = ! empty( $input['feat_result_count'] ) ? 1 : 0;
		$out['auto_cleanup']      = ! empty( $input['auto_cleanup'] ) ? 1 : 0;

		// Languages.
		$langs = isset( $input['languages'] ) ? (array) $input['languages'] : array( 'en', 'bn' );
		$langs = array_filter( array_map( 'sanitize_key', $langs ) );
		$out['languages'] = ! empty( $langs ) ? array_values( array_unique( $langs ) ) : array( 'en', 'bn' );

		// CTA placement (1.0.12).
		$out['cta_mode'] = isset( $input['cta_mode'] ) && in_array( $input['cta_mode'], array( 'dropdown', 'search_page', 'hidden' ), true )
			? $input['cta_mode']
			: 'dropdown';

		// Analytics region (1.0.14). Auto tries Global first then EU.
		$out['analytics_region'] = isset( $input['analytics_region'] ) && in_array( $input['analytics_region'], array( 'auto', 'global', 'eu' ), true )
			? $input['analytics_region']
			: 'auto';

		$out['cta_max_width']     = isset( $input['cta_max_width'] )     ? max( 100, min( 2400, absint( $input['cta_max_width'] ) ) )     : 800;
		$out['cta_padding_y']     = isset( $input['cta_padding_y'] )     ? max( 0,   min( 200, absint( $input['cta_padding_y'] ) ) )     : 32;
		$out['cta_padding_x']     = isset( $input['cta_padding_x'] )     ? max( 0,   min( 200, absint( $input['cta_padding_x'] ) ) )     : 32;
		$out['cta_margin_top']    = isset( $input['cta_margin_top'] )    ? max( 0,   min( 400, absint( $input['cta_margin_top'] ) ) )    : 40;
		$out['cta_margin_bottom'] = isset( $input['cta_margin_bottom'] ) ? max( 0,   min( 400, absint( $input['cta_margin_bottom'] ) ) ) : 40;
		$out['cta_radius']        = isset( $input['cta_radius'] )        ? max( 0,   min( 100, absint( $input['cta_radius'] ) ) )        : 14;
		$out['cta_text_size']     = isset( $input['cta_text_size'] )     ? max( 8,   min( 60,  absint( $input['cta_text_size'] ) ) )     : 18;
		$out['cta_btn_size']      = isset( $input['cta_btn_size'] )      ? max( 8,   min( 60,  absint( $input['cta_btn_size'] ) ) )      : 16;
		$out['cta_bg']            = $this->sanitize_color_field( isset( $input['cta_bg'] )         ? $input['cta_bg']         : '', '#ffffff' );
		$out['cta_text_color']    = $this->sanitize_color_field( isset( $input['cta_text_color'] ) ? $input['cta_text_color'] : '', '#1a1a1a' );
		$out['cta_btn_bg']        = $this->sanitize_color_field( isset( $input['cta_btn_bg'] )     ? $input['cta_btn_bg']     : '', '#7B3FE4' );
		$out['cta_btn_color']     = $this->sanitize_color_field( isset( $input['cta_btn_color'] )  ? $input['cta_btn_color']  : '', '#ffffff' );

		$out['cta_align'] = isset( $input['cta_align'] ) && in_array( $input['cta_align'], array( 'left', 'center', 'right' ), true )
			? $input['cta_align']
			: 'center';

		return $out;
	}

	/**
	 * Sanitize a hex color (with or without '#') or fall back to default.
	 *
	 * @param string $value   Raw input.
	 * @param string $default Fallback color when input is invalid.
	 * @return string
	 */
	protected function sanitize_color_field( $value, $default = '#ffffff' ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return $default;
		}
		// Allow #rgb, #rgba, #rrggbb, #rrggbbaa.
		if ( preg_match( '/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{4}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $value, $m ) ) {
			return '#' . ltrim( $value, '#' );
		}
		return $default;
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
		$cleanup_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=zymarg_algolia_cleanup' ),
			'zymarg_algolia_cleanup'
		);

		$notice = isset( $_GET['zymarg_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['zymarg_notice'] ) ) : '';
		$msg    = isset( $_GET['zymarg_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['zymarg_msg'] ) ) : '';
		?>
		<div class="wrap">
			<h1>Search Engine</h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice ); ?> is-dismissible">
					<p><?php echo esc_html( $msg ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				Configure your Search Engine credentials, then run a full reindex. Indexing happens
				automatically in the background whenever products, categories, or vendors are added,
				updated, or removed.
			</p>

			<?php
			$admin_notes = (string) get_option( 'zymarg_algolia_admin_notes', '' );
			$notes_nonce = wp_create_nonce( 'zymarg_algolia_save_notes' );
			$results_url = apply_filters( 'zymarg_algolia_search_results_url', home_url( '/search-results/' ) );
			?>
			<div class="zymarg-ref" style="margin:14px 0 24px;padding:14px 18px;background:#f9f5ff;border:1px solid #e6d9ff;border-radius:10px;">
				<h2 class="title" style="margin-top:0;">Quick Reference &amp; Notes</h2>
				<p class="description">Everything worth remembering, in one place. Click any <code>code</code> to copy it.</p>
				<table class="widefat striped" style="background:#fff;margin:8px 0 16px;max-width:860px;">
					<tbody>
						<tr>
							<td style="width:240px;"><strong>Search bar shortcode</strong></td>
							<td><code class="zymarg-copy" title="Click to copy" style="cursor:pointer;">[zymarg_algolia_search]</code></td>
						</tr>
						<tr>
							<td><strong>Icon-only search bar</strong></td>
							<td><code class="zymarg-copy" title="Click to copy" style="cursor:pointer;">[zymarg_algolia_search icon_only="1"]</code></td>
						</tr>
						<tr>
							<td><strong>Add to header (Elementor)</strong></td>
							<td>Edit your header &rarr; search the panel for <strong>"ZYMARG Search"</strong> (under the <em>ZYMARG</em> category) &rarr; drag it in. The Icon-only toggle is in the widget's Content tab.</td>
						</tr>
						<tr>
							<td><strong>Search results page</strong></td>
							<td><code class="zymarg-copy" title="Click to copy" style="cursor:pointer;"><?php echo esc_html( trailingslashit( $results_url ) ); ?>?q=KEYWORD</code> &mdash; the dropdown's "See all results" and the Enter key both go here.</td>
						</tr>
						<tr>
							<td><strong>Results per batch</strong></td>
							<td>20 per scroll (set in the <em>ZYMARG Search Result Page</em> plugin).</td>
						</tr>
						<tr>
							<td><strong>Out-of-stock products</strong></td>
							<td>Shown in search results (kept in the index on purpose).</td>
						</tr>
						<tr>
							<td><strong>Orphaned records</strong></td>
							<td>Cleaned automatically every day. Manual cleanup: the <strong>"Remove orphaned records"</strong> button further down this page.</td>
						</tr>
						<tr>
							<td><strong>After bulk-adding products</strong></td>
							<td>Optional: click <strong>"Reindex everything now"</strong> below (it also clears orphans).</td>
						</tr>
					</tbody>
				</table>

				<label for="zymarg-admin-notes" style="font-weight:600;display:block;margin-bottom:4px;">My notes <span style="font-weight:400;color:#666;">(saved automatically)</span></label>
				<textarea id="zymarg-admin-notes" rows="5" class="large-text" style="max-width:860px;" placeholder="Write anything you want to remember — credentials reminders, custom slugs, to-dos…"><?php echo esc_textarea( $admin_notes ); ?></textarea>
				<p style="margin:6px 0 0;">
					<button type="button" class="button button-secondary" id="zymarg-save-notes">Save notes</button>
					<span id="zymarg-notes-status" style="margin-left:10px;font-weight:600;"></span>
				</p>
			</div>
			<script>
			(function () {
				var ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var nonce   = <?php echo wp_json_encode( $notes_nonce ); ?>;

				// Click-to-copy.
				Array.prototype.forEach.call(document.querySelectorAll('.zymarg-copy'), function (el) {
					el.addEventListener('click', function () {
						var text = el.textContent.trim();
						var done = function () {
							var prev = el.style.background;
							el.style.background = '#d4f5dd';
							setTimeout(function () { el.style.background = prev; }, 700);
						};
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(text).then(done).catch(done);
						} else { done(); }
					});
				});

				// Auto-saving notes (AJAX).
				var ta     = document.getElementById('zymarg-admin-notes');
				var btn    = document.getElementById('zymarg-save-notes');
				var status = document.getElementById('zymarg-notes-status');
				var timer;
				function save() {
					if (!ta) return;
					status.textContent = 'Saving…'; status.style.color = '#666';
					var body = 'action=zymarg_algolia_save_notes&nonce=' + encodeURIComponent(nonce) +
						'&notes=' + encodeURIComponent(ta.value);
					fetch(ajaxurl, {
						method: 'POST', credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body
					}).then(function (r) { return r.json(); })
					  .then(function (res) {
						var ok = res && res.success;
						status.textContent = ok ? 'Saved' : 'Save failed';
						status.style.color = ok ? '#1a7f37' : '#b00';
						setTimeout(function () { status.textContent = ''; }, 2000);
					  }).catch(function () {
						status.textContent = 'Save failed'; status.style.color = '#b00';
					  });
				}
				if (btn) btn.addEventListener('click', save);
				if (ta)  ta.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(save, 1200); });
			})();
			</script>


			<form method="post" action="options.php">
				<?php settings_fields( 'zymarg_algolia_group' ); ?>

				<h2 class="title">Search Engine credentials</h2>
				<p class="description">
					Get these from your search service dashboard: API Keys section.
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
					<tr>
						<th scope="row"><label for="zymarg_analytics_region">Analytics region</label></th>
						<td>
							<?php $region = isset( $settings['analytics_region'] ) ? $settings['analytics_region'] : 'auto'; ?>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[analytics_region]" id="zymarg_analytics_region">
								<option value="auto"   <?php selected( 'auto',   $region ); ?>>Auto-detect (try Global, fall back to EU)</option>
								<option value="global" <?php selected( 'global', $region ); ?>>Global / US (analytics.algolia.com)</option>
								<option value="eu"     <?php selected( 'eu',     $region ); ?>>EU / Germany / UK (analytics.de.algolia.com)</option>
							</select>
							<p class="description">
								Search analytics is segregated by cluster region. If your cluster is in the UK, Germany, France or any EU region, choose <strong>EU</strong>.
								If you're not sure, pick <strong>Auto-detect</strong> — the dashboard will probe both endpoints and use whichever returns data.
								Check your cluster region in the <a href="https://dashboard.algolia.com/account/applications/" target="_blank" rel="noopener">search service dashboard → Applications</a> column "Cluster".
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Smart Features <span style="font-weight:400;color:#666;">(new in 2.0.0)</span></h2>
				<p class="description" style="max-width:760px;">
					Turn any feature on or off. Everything is <strong>ON by default</strong> and matches how the
					search bar already works. Turning one off only disables that one thing — your dropdown design,
					animation, and the search-results page are never affected. <strong>This box is your reminder of
					what each feature does.</strong>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Recent searches</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[feat_recent]" value="1"
									<?php checked( ! empty( $settings['feat_recent'] ) ); ?> /> Enable
							</label>
							<p class="description">Shows the visitor their last few searches when they focus the empty search box. For logged-in customers these also sync across their devices. Stored per-user; nothing extra is sent to the search service.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Keyboard navigation</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[feat_keyboard]" value="1"
									<?php checked( ! empty( $settings['feat_keyboard'] ) ); ?> /> Enable
							</label>
							<p class="description">Lets users move through dropdown results with the Up/Down arrow keys and open one with Enter. The Escape-to-close shortcut always stays on. No visual change.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Click tracking (Insights)</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[feat_insights]" value="1"
									<?php checked( ! empty( $settings['feat_insights'] ) ); ?> /> Enable
							</label>
							<p class="description">Sends an anonymous "this result was clicked" event to the search service so popular products gradually rank higher. No personal data. Works on the free tier. Turn off if you don't want any click events sent.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">"Did you mean" related results</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[feat_related]" value="1"
									<?php checked( ! empty( $settings['feat_related'] ) ); ?> /> Enable
							</label>
							<p class="description">When a search has zero exact matches, the dropdown automatically shows close/related products under a "Showing related results for ..." heading instead of the empty message. Turn off to show the no-results CTA straight away.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Result count badge</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[feat_result_count]" value="1"
									<?php checked( ! empty( $settings['feat_result_count'] ) ); ?> /> Enable
							</label>
							<p class="description">Shows the small "N results" total at the top of the dropdown. Turn off for a cleaner look.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Trending searches</th>
						<td>
							<p class="description" style="margin-top:6px;">Controlled by the <strong>"Show trending searches"</strong> switch in the <em>No-results CTA</em> section just below, along with the trending terms list. (Kept there so it sits next to the related text settings.)</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Automatic orphan cleanup</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_cleanup]" value="1"
									<?php checked( ! empty( $settings['auto_cleanup'] ) ); ?> /> Enable (runs daily in the background)
							</label>
							<p class="description">
								Automatically removes orphaned index records once a day, so you never have to click
								"Remove orphaned records" yourself. Orphans are leftovers from products removed via
								bulk edits / imports that skip the normal delete hooks. Out-of-stock products are kept.
								<?php
								$last = get_option( 'zymarg_algolia_last_cleanup' );
								if ( is_array( $last ) && ! empty( $last['time'] ) ) {
									echo '<br /><em>Last auto-cleanup: ' . esc_html( wp_date( 'M j, Y g:i A', (int) $last['time'] ) )
										. ' (' . esc_html( human_time_diff( (int) $last['time'], time() ) ) . ' ago) — removed '
										. (int) ( isset( $last['removed'] ) ? $last['removed'] : 0 ) . ' record(s).</em>';
								}
								?>
							</p>
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
					<tr>
						<th scope="row">Show trending searches</th>
						<td>
							<input type="checkbox"
								name="<?php echo esc_attr( self::OPTION ); ?>[show_trending]"
								value="1"
								<?php checked( 1, isset( $settings['show_trending'] ) ? $settings['show_trending'] : 1 ); ?> />
							<label>Display the "Trending searches" row when the search input is focused and empty</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_trending_fallback">Trending searches</label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION ); ?>[trending_fallback]" type="text"
								id="zymarg_trending_fallback"
								value="<?php echo esc_attr( isset( $settings['trending_fallback'] ) ? $settings['trending_fallback'] : '' ); ?>"
								class="large-text"
								placeholder="iPhone, Wireless Earbuds, Gaming Chair, Smartwatch, Laptop, Mechanical Keyboard" />
							<p class="description">Comma-separated list of trending terms shown on the search bar before the user types anything. Displayed when real analytics data is not yet available. Maximum 6 terms used. Leave blank to use the built-in defaults.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">CTA placement</h2>
				<p class="description">Choose <em>where</em> the "Couldn't find / Request Here" call-to-action shows up. The Message and Button label above are reused across all three modes.</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Display mode</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cta_mode]" value="dropdown"
										<?php checked( 'dropdown', $settings['cta_mode'] ); ?> />
									<strong>Show in dropdown</strong> (only when zero results match — current default)
								</label>
								<br>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cta_mode]" value="search_page"
										<?php checked( 'search_page', $settings['cta_mode'] ); ?> />
									<strong>Show on the search results page</strong> (banner below WP search results, always visible)
								</label>
								<br>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cta_mode]" value="hidden"
										<?php checked( 'hidden', $settings['cta_mode'] ); ?> />
									<strong>Hidden everywhere</strong> (completely disabled)
								</label>
							</fieldset>
							<p class="description">When "Show on the search results page" is selected, the dropdown CTA is automatically hidden so the user only sees one CTA at a time.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Banner styling (search-results-page mode)</h2>
				<p class="description">All values below only take effect when "Show on the search results page" is selected above. You can also place the banner manually anywhere with the shortcode <code>[zymarg_search_cta]</code> — useful on Elementor Pro custom search templates.</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="zymarg_cta_max_width">Max width (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_max_width]"
								id="zymarg_cta_max_width" min="100" max="2400" step="10"
								value="<?php echo esc_attr( $settings['cta_max_width'] ); ?>" /> px
							<p class="description">Banner content area width. Set to 2400 for full-width if your container is wider.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_padding_y">Vertical padding (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_padding_y]"
								id="zymarg_cta_padding_y" min="0" max="200" step="1"
								value="<?php echo esc_attr( $settings['cta_padding_y'] ); ?>" /> px
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_padding_x">Horizontal padding (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_padding_x]"
								id="zymarg_cta_padding_x" min="0" max="200" step="1"
								value="<?php echo esc_attr( $settings['cta_padding_x'] ); ?>" /> px
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_margin_top">Margin top (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_margin_top]"
								id="zymarg_cta_margin_top" min="0" max="400" step="1"
								value="<?php echo esc_attr( $settings['cta_margin_top'] ); ?>" /> px
							<p class="description">Space between the search results and the top of the banner.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_margin_bottom">Margin bottom (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_margin_bottom]"
								id="zymarg_cta_margin_bottom" min="0" max="400" step="1"
								value="<?php echo esc_attr( $settings['cta_margin_bottom'] ); ?>" /> px
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_radius">Border radius (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_radius]"
								id="zymarg_cta_radius" min="0" max="100" step="1"
								value="<?php echo esc_attr( $settings['cta_radius'] ); ?>" /> px
						</td>
					</tr>
					<tr>
						<th scope="row">Alignment</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cta_align]" value="left"
										<?php checked( 'left', $settings['cta_align'] ); ?> /> Left
								</label>
								&nbsp;
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cta_align]" value="center"
										<?php checked( 'center', $settings['cta_align'] ); ?> /> Center
								</label>
								&nbsp;
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cta_align]" value="right"
										<?php checked( 'right', $settings['cta_align'] ); ?> /> Right
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_text_size">Message text size (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_text_size]"
								id="zymarg_cta_text_size" min="8" max="60" step="1"
								value="<?php echo esc_attr( $settings['cta_text_size'] ); ?>" /> px
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_btn_size">Button text size (px)</label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[cta_btn_size]"
								id="zymarg_cta_btn_size" min="8" max="60" step="1"
								value="<?php echo esc_attr( $settings['cta_btn_size'] ); ?>" /> px
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_bg">Banner background</label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[cta_bg]"
								id="zymarg_cta_bg" class="regular-text"
								value="<?php echo esc_attr( $settings['cta_bg'] ); ?>"
								placeholder="#ffffff" />
							<input type="color"
								value="<?php echo esc_attr( $settings['cta_bg'] ); ?>"
								oninput="document.getElementById('zymarg_cta_bg').value=this.value" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_text_color">Message text color</label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[cta_text_color]"
								id="zymarg_cta_text_color" class="regular-text"
								value="<?php echo esc_attr( $settings['cta_text_color'] ); ?>"
								placeholder="#1a1a1a" />
							<input type="color"
								value="<?php echo esc_attr( $settings['cta_text_color'] ); ?>"
								oninput="document.getElementById('zymarg_cta_text_color').value=this.value" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_btn_bg">Button background</label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[cta_btn_bg]"
								id="zymarg_cta_btn_bg" class="regular-text"
								value="<?php echo esc_attr( $settings['cta_btn_bg'] ); ?>"
								placeholder="#7B3FE4" />
							<input type="color"
								value="<?php echo esc_attr( $settings['cta_btn_bg'] ); ?>"
								oninput="document.getElementById('zymarg_cta_btn_bg').value=this.value" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zymarg_cta_btn_color">Button text color</label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[cta_btn_color]"
								id="zymarg_cta_btn_color" class="regular-text"
								value="<?php echo esc_attr( $settings['cta_btn_color'] ); ?>"
								placeholder="#ffffff" />
							<input type="color"
								value="<?php echo esc_attr( $settings['cta_btn_color'] ); ?>"
								oninput="document.getElementById('zymarg_cta_btn_color').value=this.value" />
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
				&nbsp;
				<a class="button button-secondary" href="<?php echo esc_url( $cleanup_url ); ?>"
					onclick="return confirm('Remove orphaned records from the index now?');">
					Remove orphaned records
				</a>
			</p>
			<p class="description">
				<strong>Remove orphaned records</strong> deletes index entries for products that were
				deleted, trashed, or unpublished but were never removed from the search index. These leftovers pile up
				at the top of the <em>Latest</em> sort and make the search-results page slow and incomplete.
				Out-of-stock products are kept. (This also runs automatically with every reindex.)
			</p>

			<h2 class="title">How to add the search bar</h2>
			<p>Pick whichever method matches the page builder you use. <strong>No shortcode required</strong> — that's only kept for backwards compatibility.</p>
			<ol>
				<li>First, save credentials above, click <em>Verify connection</em>, then <em>Reindex everything now</em>.</li>
				<li><strong>Elementor (recommended for Astra header):</strong> open your header template, search the panel for <em>"ZYMARG Search"</em> (under the <em>ZYMARG</em> category), and drag it into the header. The search bar shows live in the editor — no need to publish to see it.</li>
				<li><strong>Gutenberg / Site Editor:</strong> click the <em>+</em> inserter, search <em>"ZYMARG Search"</em>, click to drop it in. Use the sidebar to set a custom placeholder.</li>
				<li><strong>Appearance &rarr; Widgets:</strong> drop the <em>"ZYMARG Search"</em> widget into any sidebar / header widget zone (Astra theme widget areas, footer columns, etc).</li>
				<li><em>(Optional)</em> Legacy: the shortcode <code>[zymarg_algolia_search]</code> still works anywhere shortcodes are accepted.</li>
			</ol>
		</div>
		<?php
	}

	public function handle_verify() {
		check_admin_referer( 'zymarg_algolia_verify' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		$app_id    = trim( (string) zymarg_algolia_get_setting( 'app_id' ) );
		$admin_key = trim( (string) zymarg_algolia_get_setting( 'admin_api_key' ) );

		if ( '' === $app_id || '' === $admin_key ) {
			$this->redirect_with_notice(
				'error',
				'Application ID or Admin API Key is empty. Click "Save settings" first, then try Verify again.'
			);
			return;
		}

		// Quick sanity check on the App ID format (Algolia App IDs are exactly 10 uppercase alphanumerics).
		if ( ! preg_match( '/^[A-Z0-9]{8,16}$/', $app_id ) ) {
			$this->redirect_with_notice(
				'error',
				sprintf(
					'Application ID looks malformed (got "%s"). It should be 10 uppercase letters/digits, e.g. "L8K9XQYZAB". Re-copy it from your search service dashboard.',
					esc_html( $app_id )
				)
			);
			return;
		}

		$client = new Zymarg_Algolia_Client();
		$result = $client->verify();

		if ( is_wp_error( $result ) ) {
			$debug    = $client->get_last_debug();
			$debug_str = '';
			if ( ! empty( $debug ) ) {
				$debug_str = sprintf( ' [URL: %s | HTTP %d]', $debug['url'], (int) $debug['code'] );
			}
			$this->redirect_with_notice(
				'error',
				'Search Engine connection failed: ' . $result->get_error_message() . $debug_str
			);
			return;
		}

		$count = isset( $result['items'] ) ? count( $result['items'] ) : 0;
		$this->redirect_with_notice(
			'success',
			sprintf( 'Search Engine connection OK. Found %d existing indices in your application.', $count )
		);
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

		// Also purge any orphaned records left from deleted/unpublished items
		// so they can't pile up at the top of the "Latest" sort.
		$removed  = 0;
		$removed += (int) $products->cleanup_orphans();
		$removed += (int) $vendors->cleanup_orphans();
		$removed += (int) $categories->cleanup_orphans();

		$this->redirect_with_notice(
			'success',
			sprintf(
				'Queued %d records for reindex (runs in the background) and removed %d orphaned record(s) from the index.',
				$total,
				$removed
			)
		);
	}

	/**
	 * Remove orphaned records on demand (without a full reindex).
	 */
	public function handle_cleanup() {
		check_admin_referer( 'zymarg_algolia_cleanup' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		$client = new Zymarg_Algolia_Client();
		if ( ! $client->is_configured() ) {
			$this->redirect_with_notice( 'error', 'Search Engine credentials are not configured yet.' );
			return;
		}

		$p = (int) ( new Zymarg_Algolia_Products() )->cleanup_orphans();
		$v = (int) ( new Zymarg_Algolia_Vendors() )->cleanup_orphans();
		$c = (int) ( new Zymarg_Algolia_Categories() )->cleanup_orphans();
		$total = $p + $v + $c;

		if ( 0 === $total ) {
			$this->redirect_with_notice( 'success', 'No orphaned records found — your index is already clean.' );
			return;
		}

		$this->redirect_with_notice(
			'success',
			sprintf(
				'Removed %d orphaned record(s) — products: %d, vendors: %d, categories: %d. The "Latest" sort should now be fast and show a full page.',
				$total,
				$p,
				$v,
				$c
			)
		);
	}

	/**
	 * AJAX: save the admin's personal reference notes.
	 */
	public function ajax_save_notes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zymarg_algolia_save_notes' ) ) {
			wp_send_json_error( 'bad_nonce', 403 );
		}
		$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		update_option( 'zymarg_algolia_admin_notes', $notes, false );
		wp_send_json_success();
	}

	protected function redirect_with_notice( $type, $msg ) {
		$url = admin_url( 'admin.php?page=' . self::SLUG );
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
