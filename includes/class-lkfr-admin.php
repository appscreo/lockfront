<?php
/**
 * Admin UI – settings, logs, bypass links.
 * Uses native WordPress Settings API. No external frameworks.
 *
 * @package Lockfront
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers menus, settings, and renders all admin pages.
 */
class LKFR_Admin {

	/** register_setting() option group. */
	const OPT_GROUP = 'lkfr_settings_group';

	/** Single wp_options key that holds all settings as an array. */
	const OPT_KEY = 'lkfr_settings';

	/** Tab definitions: slug => label. */
	private static $tabs = array(
		'general'  => 'General',
		'security' => 'Brute Force',
		'bypass'   => 'Bypass URL',
		'template' => 'Template',
		'logs'     => 'Login Logs',
		'tokens'   => 'Bypass Links',
	);

	/** Tabs that render a settings form (vs. data-only tabs). */
	private static $form_tabs = array( 'general', 'security', 'bypass', 'template' );

	/** Keys owned by each settings tab (used for cross-tab preservation). */
	private static $tab_keys = array(
		'general'  => array(
			'enable_protection', 'site_password', 'unlock_duration',
			'allow_admins', 'allow_rest_api', 'allow_rss', 'ip_whitelist',
			'http_status_code',
		),
		'security' => array( 'brute_force', 'bf_max_attempts', 'bf_window' ),
		'bypass'   => array( 'bypass_key', 'bypass_value' ),
		'template' => array(
			'tpl_headline', 'tpl_subheadline', 'tpl_placeholder',
			'tpl_btn_text', 'error_message', 'tpl_footer_text',
			'tpl_form_placement',
			'tpl_bg_type',
			'tpl_bg_color',
			'tpl_bg_grad_start', 'tpl_bg_grad_end', 'tpl_bg_grad_angle',
			'tpl_bg_image', 'tpl_overlay_color', 'tpl_overlay_opacity',
			'tpl_card_bg', 'tpl_card_width', 'tpl_card_radius', 'tpl_card_shadow',
			'tpl_font_family', 'tpl_google_font',
			'tpl_heading_color', 'tpl_subtext_color',
			'tpl_logo_url', 'tpl_logo_width',
			'tpl_input_bg', 'tpl_input_border', 'tpl_input_color',
			'tpl_input_radius', 'tpl_input_focus',
			'tpl_btn_bg', 'tpl_btn_hover', 'tpl_btn_color', 'tpl_btn_radius',
			'tpl_error_color',
		),
	);

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'add_pages' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices',         array( $this, 'setup_notice' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( LKFR_PLUGIN_FILE ),
			array( $this, 'action_links' )
		);

		// AJAX
		add_action( 'wp_ajax_lkfr_preview',      array( 'LKFR_Template', 'ajax_preview' ) );
		add_action( 'wp_ajax_lkfr_create_token', array( $this, 'ajax_create_token' ) );
		add_action( 'wp_ajax_lkfr_delete_token', array( $this, 'ajax_delete_token' ) );
		add_action( 'wp_ajax_lkfr_clear_logs',   array( $this, 'ajax_clear_logs' ) );
	}

	// -----------------------------------------------------------------------
	// Menu
	// -----------------------------------------------------------------------

	public function add_pages() {
		add_menu_page(
			__( 'Lockfront', 'lockfront' ),
			__( 'Lockfront', 'lockfront' ),
			'manage_options',
			'lockfront',
			array( $this, 'page_settings' ),
			$this->menu_icon(),
			80
		);
		add_submenu_page(
			'lockfront',
			__( 'Lockfront Settings', 'lockfront' ),
			__( 'Settings', 'lockfront' ),
			'manage_options',
			'lockfront',
			array( $this, 'page_settings' )
		);
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	public function enqueue( $hook ) {
		if ( 'toplevel_page_lockfront' !== $hook ) {
			return;
		}

		// wp_enqueue_media() must be called before output to enable the
		// WP media library uploader in our image pickers.
		wp_enqueue_media();

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'lkfr-admin',
			LKFR_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			LKFR_VERSION
		);

		wp_enqueue_script(
			'lkfr-admin',
			LKFR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			LKFR_VERSION,
			true
		);

		wp_localize_script(
			'lkfr-admin',
			'LKFR',
			array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'nonce_token' => wp_create_nonce( 'lkfr_bypass_nonce' ),
				'nonce_logs'  => wp_create_nonce( 'lkfr_clear_logs' ),
				'i18n'        => array(
					'copied'      => __( 'Copied!',                                   'lockfront' ),
					'copy'        => __( 'Copy',                                      'lockfront' ),
					'copy_url'    => __( 'Copy URL',                                  'lockfront' ),
					'creating'    => __( 'Creating…',                                 'lockfront' ),
					'generate'    => __( '+ Generate Bypass Link',                    'lockfront' ),
					'no_expiry'   => __( 'Please set an expiry date and time.',       'lockfront' ),
					'err_token'   => __( 'Error creating link. Please try again.',    'lockfront' ),
					'confirm_del' => __( 'Delete this bypass link? Cannot be undone.','lockfront' ),
					'confirm_clr' => __( 'Clear all login logs? Cannot be undone.',  'lockfront' ),
					'show'        => __( 'Show',                                      'lockfront' ),
					'hide'        => __( 'Hide',                                      'lockfront' ),
				),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Settings API
	// -----------------------------------------------------------------------

	public function register_settings() {
		register_setting(
			self::OPT_GROUP,
			self::OPT_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitise and validate every option before it is written to the database.
	 *
	 * @param mixed $raw Raw POST data (expected to be an array).
	 * @return array Sanitised settings array.
	 */
	public function sanitize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		// --- Booleans (toggle checkboxes) ---
		$booleans = array(
			'enable_protection', 'allow_admins', 'allow_rest_api',
			'allow_rss', 'brute_force', 'tpl_card_shadow',
		);
		foreach ( $booleans as $k ) {
			$clean[ $k ] = ! empty( $raw[ $k ] ) ? '1' : '0';
		}

		// --- Integers with min/max clamp ---
		$ints = array(
			'unlock_duration'  => array( 0, 365 ),
			'bf_max_attempts'  => array( 1, 100 ),
			'bf_window'        => array( 1, 1440 ),
			'tpl_card_width'   => array( 280, 900 ),
			'tpl_card_radius'  => array( 0, 48 ),
			'tpl_input_radius' => array( 0, 32 ),
			'tpl_btn_radius'   => array( 0, 32 ),
			'tpl_logo_width'   => array( 40, 500 ),
			'tpl_bg_grad_angle'=> array( 0, 360 ),
		);
		foreach ( $ints as $k => $range ) {
			$v           = isset( $raw[ $k ] ) ? (int) $raw[ $k ] : $range[0];
			$clean[ $k ] = max( $range[0], min( $range[1], $v ) );
		}

		// --- Floats (0-1 range) ---
		foreach ( array( 'tpl_overlay_opacity' ) as $k ) {
			$v           = isset( $raw[ $k ] ) ? (float) $raw[ $k ] : 0.5;
			$clean[ $k ] = (string) max( 0.0, min( 1.0, $v ) );
		}

		// --- Plain text (single-line) ---
		$text_fields = array(
			'site_password', 'bypass_key', 'bypass_value',
			'tpl_headline', 'tpl_subheadline', 'tpl_placeholder',
			'tpl_btn_text', 'error_message',
			'tpl_font_family', 'tpl_google_font',
		);
		foreach ( $text_fields as $k ) {
			$clean[ $k ] = isset( $raw[ $k ] )
				? sanitize_text_field( wp_unslash( $raw[ $k ] ) )
				: '';
		}

		// --- Textarea (no HTML) ---
		$clean['ip_whitelist'] = isset( $raw['ip_whitelist'] )
			? sanitize_textarea_field( wp_unslash( $raw['ip_whitelist'] ) )
			: '';

		// --- Textarea (limited HTML) ---
		$clean['tpl_footer_text'] = isset( $raw['tpl_footer_text'] )
			? wp_kses_post( wp_unslash( $raw['tpl_footer_text'] ) )
			: '';

		// --- Hex colours ---
		$colour_fields = array(
			'tpl_bg_color', 'tpl_bg_grad_start', 'tpl_bg_grad_end',
			'tpl_overlay_color', 'tpl_card_bg',
			'tpl_heading_color', 'tpl_subtext_color',
			'tpl_input_bg', 'tpl_input_border', 'tpl_input_color', 'tpl_input_focus',
			'tpl_btn_bg', 'tpl_btn_hover', 'tpl_btn_color',
			'tpl_error_color',
		);
		foreach ( $colour_fields as $k ) {
			$clean[ $k ] = isset( $raw[ $k ] )
				? ( sanitize_hex_color( $raw[ $k ] ) ?: '' )
				: '';
		}

		// --- Selects with strict allowlists ---
		// --- HTTP status code ---
		$allowed_status = array( '200', '401', '503' );
		$clean['http_status_code'] = ( isset( $raw['http_status_code'] ) && in_array( $raw['http_status_code'], $allowed_status, true ) )
			? $raw['http_status_code']
			: '503';

		$allowed_bg = array( 'color', 'gradient', 'image' );
		$clean['tpl_bg_type'] = ( isset( $raw['tpl_bg_type'] ) && in_array( $raw['tpl_bg_type'], $allowed_bg, true ) )
			? $raw['tpl_bg_type']
			: 'color';

		$allowed_placement = array( 'center', 'left', 'right' );
		$clean['tpl_form_placement'] = ( isset( $raw['tpl_form_placement'] ) && in_array( $raw['tpl_form_placement'], $allowed_placement, true ) )
			? $raw['tpl_form_placement']
			: 'center';

		// --- URLs ---
		foreach ( array( 'tpl_bg_image', 'tpl_logo_url' ) as $k ) {
			$clean[ $k ] = isset( $raw[ $k ] )
				? esc_url_raw( wp_unslash( $raw[ $k ] ) )
				: '';
		}

		return $clean;
	}

	// -----------------------------------------------------------------------
	// Main settings page
	// -----------------------------------------------------------------------

	/** Render the single settings page (all tabs). */
	public function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		if ( ! array_key_exists( $tab, self::$tabs ) ) {
			$tab = 'general';
		}

		$is_form = in_array( $tab, self::$form_tabs, true );
		?>
		<div class="wrap lkfr-wrap">

			<!-- Page heading -->
			<h1 class="lkfr-page-heading">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				<?php esc_html_e( 'Lockfront', 'lockfront' ); ?>
			</h1>

			<!-- Flat WooCommerce-style tab bar -->
			<nav class="lkfr-tabs" aria-label="<?php esc_attr_e( 'Lockfront sections', 'lockfront' ); ?>">
				<?php foreach ( self::$tabs as $slug => $label ) :
					$url    = admin_url( 'admin.php?page=lockfront&tab=' . $slug );
					$active = ( $tab === $slug );
				?>
				<a href="<?php echo esc_url( $url ); ?>"
				   class="lkfr-tab<?php echo $active ? ' lkfr-tab--active' : ''; ?>"
				   <?php echo $active ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( __( $label, 'lockfront' ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<div class="lkfr-panel">
			<?php if ( $is_form ) : ?>

				<?php settings_errors( self::OPT_KEY ); ?>

				<form method="post" action="options.php" novalidate>
					<?php
					settings_fields( self::OPT_GROUP );
					$this->hidden_preserve( $tab );
					?>

					<?php
					switch ( $tab ) {
						case 'general':  $this->tab_general();  break;
						case 'security': $this->tab_security(); break;
						case 'bypass':   $this->tab_bypass();   break;
						case 'template': $this->tab_template(); break;
					}
					?>

					<div class="lkfr-save-bar">
						<?php submit_button( __( 'Save Changes', 'lockfront' ), 'primary large', 'submit', false ); ?>
					</div>
				</form>

			<?php elseif ( 'logs' === $tab ) : ?>
				<?php $this->tab_logs(); ?>
			<?php elseif ( 'tokens' === $tab ) : ?>
				<?php $this->tab_tokens(); ?>
			<?php endif; ?>
			</div><!-- .lkfr-panel -->

		</div><!-- .lkfr-wrap -->
		<?php
	}

	/**
	 * Emit hidden inputs for all keys NOT belonging to the current tab,
	 * so WP Settings API preserves the full option array on save.
	 *
	 * @param string $current_tab Active tab slug.
	 */
	private function hidden_preserve( $current_tab ) {
		$current_keys = self::$tab_keys[ $current_tab ] ?? array();
		$all_keys     = array_merge( ...array_values( self::$tab_keys ) );

		foreach ( array_diff( $all_keys, $current_keys ) as $key ) {
			printf(
				'<input type="hidden" name="%s" value="%s">' . "\n",
				esc_attr( self::OPT_KEY . '[' . $key . ']' ),
				esc_attr( (string) lkfr_get( $key, '' ) )
			);
		}
	}

	// -----------------------------------------------------------------------
	// Tab: General
	// -----------------------------------------------------------------------

	private function tab_general() {
		?>
		<h2><?php esc_html_e( 'Protection', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php $this->row_toggle( 'enable_protection',
				__( 'Enable Protection', 'lockfront' ),
				__( 'When on, visitors must enter the password to access the front-end.', 'lockfront' )
			); ?>
			<tr>
				<th scope="row">
					<label for="lkfr-site-password"><?php esc_html_e( 'Site Password', 'lockfront' ); ?></label>
				</th>
				<td>
					<div style="display:flex;align-items:center;gap:8px">
						<input type="password"
							id="lkfr-site-password"
							name="<?php echo esc_attr( self::OPT_KEY . '[site_password]' ); ?>"
							value="<?php echo esc_attr( lkfr_get( 'site_password' ) ); ?>"
							class="regular-text"
							autocomplete="new-password">
						<button type="button" class="button lkfr-pw-toggle" data-target="lkfr-site-password">
							<?php esc_html_e( 'Show', 'lockfront' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'The password visitors must enter. Store it safely.', 'lockfront' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lkfr-dur"><?php esc_html_e( 'Unlock Duration', 'lockfront' ); ?></label>
				</th>
				<td>
					<input type="number" id="lkfr-dur" min="0" max="365" class="small-text"
						name="<?php echo esc_attr( self::OPT_KEY . '[unlock_duration]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'unlock_duration', '1' ) ); ?>">
					<?php esc_html_e( 'days', 'lockfront' ); ?>
					<p class="description"><?php esc_html_e( 'How long until the visitor must re-enter the password. 0 = current browser session only.', 'lockfront' ); ?></p>
				</td>
			</tr>
		</table>

		<hr>

		<h2><?php esc_html_e( 'HTTP Response Code', 'lockfront' ); ?></h2>
		<p><?php esc_html_e( 'The HTTP status code sent to browsers and search-engine crawlers when the password page is shown.', 'lockfront' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status Code', 'lockfront' ); ?></th>
				<td>
					<?php
					$http_code = lkfr_get( 'http_status_code', '503' );
					$http_opts = array(
						'503' => __( '503 Service Unavailable &mdash; Recommended for maintenance. Sends <code>Retry-After: 3600</code> so Google will not deindex your site.', 'lockfront' ),
						'401' => __( '401 Unauthorized &mdash; Semantically correct for password-protected content. Most crawlers treat it like 403 and stop indexing.', 'lockfront' ),
						'200' => __( '200 OK &mdash; Silent pass-through. The page looks normal to crawlers, which may index the password page.', 'lockfront' ),
					);
					foreach ( $http_opts as $val => $lbl ) : ?>
					<label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;max-width:640px">
						<input type="radio" style="margin-top:3px;flex-shrink:0"
							name="<?php echo esc_attr( self::OPT_KEY . '[http_status_code]' ); ?>"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $http_code, $val ); ?>>
						<span><?php echo wp_kses( $lbl, array( 'code' => array() ) ); ?></span>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<hr>

		<h2><?php esc_html_e( 'Access Rules', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->row_toggle( 'allow_admins',
				__( 'Bypass for Logged-in Admins', 'lockfront' ),
				__( 'WordPress administrators already logged in skip the password page.', 'lockfront' )
			);
			$this->row_toggle( 'allow_rest_api',
				__( 'Allow REST API', 'lockfront' ),
				__( 'Do not block /wp-json/ requests.', 'lockfront' )
			);
			$this->row_toggle( 'allow_rss',
				__( 'Allow RSS / Feeds', 'lockfront' ),
				__( 'Do not block RSS and Atom feed requests.', 'lockfront' )
			);
			?>
			<tr>
				<th scope="row">
					<label for="lkfr-ip-wl"><?php esc_html_e( 'IP Whitelist', 'lockfront' ); ?></label>
				</th>
				<td>
					<textarea id="lkfr-ip-wl" rows="5" class="large-text"
						placeholder="192.168.1.1&#10;10.0.0.0/8"
						name="<?php echo esc_attr( self::OPT_KEY . '[ip_whitelist]' ); ?>"><?php echo esc_textarea( lkfr_get( 'ip_whitelist' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One IP per line. CIDR notation supported (e.g. 192.168.0.0/24). These IPs bypass the password page entirely.', 'lockfront' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Brute Force
	// -----------------------------------------------------------------------

	private function tab_security() {
		?>
		<h2><?php esc_html_e( 'Brute Force Protection', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php $this->row_toggle( 'brute_force',
				__( 'Enable Brute Force Protection', 'lockfront' ),
				__( 'Lock out IPs that fail too many password attempts within a time window.', 'lockfront' )
			); ?>
			<tr>
				<th scope="row">
					<label for="lkfr-bfm"><?php esc_html_e( 'Max Attempts', 'lockfront' ); ?></label>
				</th>
				<td>
					<input type="number" id="lkfr-bfm" min="1" max="100" class="small-text"
						name="<?php echo esc_attr( self::OPT_KEY . '[bf_max_attempts]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'bf_max_attempts', '5' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Failed attempts before an IP is locked out.', 'lockfront' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lkfr-bfw"><?php esc_html_e( 'Lockout Window', 'lockfront' ); ?></label>
				</th>
				<td>
					<input type="number" id="lkfr-bfw" min="1" max="1440" class="small-text"
						name="<?php echo esc_attr( self::OPT_KEY . '[bf_window]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'bf_window', '15' ) ); ?>">
					<?php esc_html_e( 'minutes', 'lockfront' ); ?>
					<p class="description"><?php esc_html_e( 'Rolling window in which failed attempts are counted. Resets automatically after this period.', 'lockfront' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Bypass URL
	// -----------------------------------------------------------------------

	private function tab_bypass() {
		?>
		<h2><?php esc_html_e( 'URL Key Bypass', 'lockfront' ); ?></h2>
		<p><?php
			echo wp_kses(
				sprintf(
					/* translators: %s: example URL */
					__( 'Anyone visiting a URL that contains this key=value pair will bypass the password page for 8 hours. Example: %s', 'lockfront' ),
					'<code>https://yoursite.com/?preview=secret123</code>'
				),
				array( 'code' => array() )
			);
		?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="lkfr-bk"><?php esc_html_e( 'URL Parameter Name', 'lockfront' ); ?></label>
				</th>
				<td>
					<input type="text" id="lkfr-bk" class="regular-text" placeholder="preview"
						name="<?php echo esc_attr( self::OPT_KEY . '[bypass_key]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'bypass_key' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Leave both fields empty to disable URL key bypass.', 'lockfront' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="lkfr-bv"><?php esc_html_e( 'URL Parameter Value (secret)', 'lockfront' ); ?></label>
				</th>
				<td>
					<input type="text" id="lkfr-bv" class="regular-text" placeholder="secret123"
						name="<?php echo esc_attr( self::OPT_KEY . '[bypass_value]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'bypass_value' ) ); ?>">
				</td>
			</tr>
		</table>

		<hr>

		<p><?php
			echo wp_kses(
				sprintf(
					/* translators: %s: link to Bypass Links tab */
					__( 'For time-limited one-time links see the %s tab.', 'lockfront' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=lockfront&tab=tokens' ) ) . '">'
						. esc_html__( 'Bypass Links', 'lockfront' ) . '</a>'
				),
				array( 'a' => array( 'href' => array() ) )
			);
		?></p>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Template
	// -----------------------------------------------------------------------

	private function tab_template() {
		$preview_url = add_query_arg(
			array(
				'action' => 'lkfr_preview',
				'nonce'  => wp_create_nonce( 'lkfr_preview' ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>

		<!-- Preview bar -->
		<div style="display:flex;align-items:center;gap:12px;padding-bottom:20px;border-bottom:1px solid #f0f0f1;margin-bottom:24px">
			<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button button-secondary">
				&#128065; <?php esc_html_e( 'Preview Login Page', 'lockfront' ); ?>
			</a>
			<span class="description"><?php esc_html_e( 'Save first to see your latest changes.', 'lockfront' ); ?></span>
		</div>

		<!-- Text & Content -->
		<h2><?php esc_html_e( 'Text &amp; Content', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->row_text( 'tpl_headline',    __( 'Headline', 'lockfront' ),        'large-text',   '', __( 'This site is under development', 'lockfront' ) );
			$this->row_text( 'tpl_subheadline', __( 'Sub-headline', 'lockfront' ),     'large-text',   '', __( 'Please enter the password to continue.', 'lockfront' ) );
			$this->row_text( 'tpl_placeholder', __( 'Input Placeholder', 'lockfront' ),'regular-text', '', __( 'Enter password…', 'lockfront' ) );
			$this->row_text( 'tpl_btn_text',    __( 'Button Label', 'lockfront' ),     'regular-text', '', __( 'Unlock', 'lockfront' ) );
			$this->row_text( 'error_message',   __( 'Error Message', 'lockfront' ),    'large-text',   __( 'Shown when an incorrect password is entered.', 'lockfront' ), __( 'Incorrect password. Please try again.', 'lockfront' ) );
			?>
			<tr>
				<th scope="row"><label for="lkfr-footer"><?php esc_html_e( 'Footer Text', 'lockfront' ); ?></label></th>
				<td>
					<textarea id="lkfr-footer" rows="3" class="large-text"
						name="<?php echo esc_attr( self::OPT_KEY . '[tpl_footer_text]' ); ?>"><?php echo esc_textarea( lkfr_get( 'tpl_footer_text' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown below the form. Basic HTML allowed (links, bold, etc.).', 'lockfront' ); ?></p>
				</td>
			</tr>
		</table>

		<hr>

		<!-- Layout -->
		<h2><?php esc_html_e( 'Layout', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Form Placement', 'lockfront' ); ?></th>
				<td>
					<?php
					$placement = lkfr_get( 'tpl_form_placement', 'center' );
					$opts      = array(
						'center' => __( 'Center', 'lockfront' ),
						'left'   => __( 'Left (vertically centred)', 'lockfront' ),
						'right'  => __( 'Right (vertically centred)', 'lockfront' ),
					);
					foreach ( $opts as $val => $lbl ) : ?>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px">
						<input type="radio"
							name="<?php echo esc_attr( self::OPT_KEY . '[tpl_form_placement]' ); ?>"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $placement, $val ); ?>>
						<?php echo esc_html( $lbl ); ?>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<hr>

		<!-- Background -->
		<h2><?php esc_html_e( 'Background', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Background Type', 'lockfront' ); ?></th>
				<td>
					<?php
					$bg_type = lkfr_get( 'tpl_bg_type', 'color' );
					$bg_opts = array(
						'color'    => __( 'Solid Color', 'lockfront' ),
						'gradient' => __( 'Gradient', 'lockfront' ),
						'image'    => __( 'Image', 'lockfront' ),
					);
					foreach ( $bg_opts as $val => $lbl ) : ?>
					<label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px">
						<input type="radio" class="lkfr-bg-type-radio"
							name="<?php echo esc_attr( self::OPT_KEY . '[tpl_bg_type]' ); ?>"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $bg_type, $val ); ?>>
						<?php echo esc_html( $lbl ); ?>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>

			<!-- Solid colour -->
			<tbody class="lkfr-bg-rows lkfr-bg-color" <?php echo ( 'color' !== $bg_type ) ? 'style="display:none"' : ''; ?>>
				<?php $this->row_colour( 'tpl_bg_color', __( 'Background Color', 'lockfront' ), '#1a1a2e' ); ?>
			</tbody>

			<!-- Gradient -->
			<tbody class="lkfr-bg-rows lkfr-bg-gradient" <?php echo ( 'gradient' !== $bg_type ) ? 'style="display:none"' : ''; ?>>
				<?php $this->row_colour( 'tpl_bg_grad_start', __( 'Gradient Start Color', 'lockfront' ), '#1a1a2e' ); ?>
				<?php $this->row_colour( 'tpl_bg_grad_end',   __( 'Gradient End Color', 'lockfront' ),   '#16213e' ); ?>
				<tr>
					<th scope="row"><label for="lkfr-grad-angle"><?php esc_html_e( 'Gradient Direction', 'lockfront' ); ?></label></th>
					<td>
						<input type="range" id="lkfr-grad-angle" min="0" max="360" step="1" class="lkfr-range"
							name="<?php echo esc_attr( self::OPT_KEY . '[tpl_bg_grad_angle]' ); ?>"
							value="<?php echo esc_attr( lkfr_get( 'tpl_bg_grad_angle', '135' ) ); ?>">
						<span class="lkfr-range-val"><?php echo esc_html( lkfr_get( 'tpl_bg_grad_angle', '135' ) ); ?></span>°
						<p class="description"><?php esc_html_e( '0° = bottom to top, 90° = left to right, 135° = diagonal top-left to bottom-right.', 'lockfront' ); ?></p>
						<!-- Live gradient preview swatch -->
						<div id="lkfr-grad-preview" style="margin-top:10px;width:200px;height:40px;border-radius:6px;border:1px solid #ddd"></div>
					</td>
				</tr>
			</tbody>

			<!-- Image -->
			<tbody class="lkfr-bg-rows lkfr-bg-image" <?php echo ( 'image' !== $bg_type ) ? 'style="display:none"' : ''; ?>>
				<?php $this->row_image( 'tpl_bg_image', __( 'Background Image', 'lockfront' ), __( 'Displayed when Background Type is Image.', 'lockfront' ) ); ?>
				<?php $this->row_colour( 'tpl_overlay_color', __( 'Image Overlay Color', 'lockfront' ), '#000000' ); ?>
				<tr>
					<th scope="row"><label for="lkfr-ov-op"><?php esc_html_e( 'Overlay Opacity', 'lockfront' ); ?></label></th>
					<td>
						<input type="range" id="lkfr-ov-op" min="0" max="1" step="0.05" class="lkfr-range"
							name="<?php echo esc_attr( self::OPT_KEY . '[tpl_overlay_opacity]' ); ?>"
							value="<?php echo esc_attr( lkfr_get( 'tpl_overlay_opacity', '0.5' ) ); ?>">
						<span class="lkfr-range-val"><?php echo esc_html( lkfr_get( 'tpl_overlay_opacity', '0.5' ) ); ?></span>
					</td>
				</tr>
			</tbody>
		</table>

		<hr>

		<!-- Card -->
		<h2><?php esc_html_e( 'Card', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php $this->row_colour( 'tpl_card_bg', __( 'Card Background', 'lockfront' ), '#ffffff' ); ?>
			<tr>
				<th scope="row"><label for="lkfr-cw"><?php esc_html_e( 'Card Max Width', 'lockfront' ); ?></label></th>
				<td>
					<input type="number" id="lkfr-cw" min="280" max="900" class="small-text"
						name="<?php echo esc_attr( self::OPT_KEY . '[tpl_card_width]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'tpl_card_width', '440' ) ); ?>"> px
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lkfr-cr"><?php esc_html_e( 'Card Border Radius', 'lockfront' ); ?></label></th>
				<td>
					<input type="range" id="lkfr-cr" min="0" max="48" step="1" class="lkfr-range"
						name="<?php echo esc_attr( self::OPT_KEY . '[tpl_card_radius]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'tpl_card_radius', '16' ) ); ?>">
					<span class="lkfr-range-val"><?php echo esc_html( lkfr_get( 'tpl_card_radius', '16' ) ); ?></span> px
				</td>
			</tr>
			<?php $this->row_toggle( 'tpl_card_shadow', __( 'Card Drop Shadow', 'lockfront' ), __( 'Adds a soft shadow beneath the card.', 'lockfront' ) ); ?>
		</table>

		<hr>

		<!-- Typography -->
		<h2><?php esc_html_e( 'Typography', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lkfr-gfont"><?php esc_html_e( 'Google Font', 'lockfront' ); ?></label></th>
				<td>
					<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
						<input type="text" id="lkfr-gfont" class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. Poppins, Roboto, Lato', 'lockfront' ); ?>"
							name="<?php echo esc_attr( self::OPT_KEY . '[tpl_google_font]' ); ?>"
							value="<?php echo esc_attr( lkfr_get( 'tpl_google_font' ) ); ?>">
						<button type="button" class="button" id="lkfr-gfont-preview"><?php esc_html_e( 'Preview', 'lockfront' ); ?></button>
					</div>
					<p class="description">
						<?php esc_html_e( 'Enter a Google Fonts name; it loads automatically on the login page. Leave empty to use the CSS Font Family below.', 'lockfront' ); ?>
						<a href="https://fonts.google.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Browse fonts →', 'lockfront' ); ?></a>
					</p>
					<div id="lkfr-gfont-sample" style="display:none;margin-top:8px;font-size:18px;padding:8px 12px;background:#f6f7f7;border-radius:4px;border:1px solid #ddd"></div>
				</td>
			</tr>
			<?php $this->row_text( 'tpl_font_family', __( 'CSS Font Family (fallback)', 'lockfront' ), 'large-text',
				__( 'Used when no Google Font is set. Any valid CSS font-family value.', 'lockfront' ),
				"'Inter','Helvetica Neue',Arial,sans-serif"
			); ?>
			<?php $this->row_colour( 'tpl_heading_color', __( 'Heading Color', 'lockfront' ),  '#1a1a2e' ); ?>
			<?php $this->row_colour( 'tpl_subtext_color', __( 'Sub-text Color', 'lockfront' ),  '#666666' ); ?>
		</table>

		<hr>

		<!-- Logo -->
		<h2><?php esc_html_e( 'Logo', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php $this->row_image( 'tpl_logo_url', __( 'Logo Image', 'lockfront' ),
				__( 'Replaces the default lock icon. Leave empty to keep the lock icon.', 'lockfront' )
			); ?>
			<tr>
				<th scope="row"><label for="lkfr-lw"><?php esc_html_e( 'Logo Max Width', 'lockfront' ); ?></label></th>
				<td>
					<input type="number" id="lkfr-lw" min="40" max="500" class="small-text"
						name="<?php echo esc_attr( self::OPT_KEY . '[tpl_logo_width]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'tpl_logo_width', '120' ) ); ?>"> px
				</td>
			</tr>
		</table>

		<hr>

		<!-- Form Colors -->
		<h2><?php esc_html_e( 'Form Colors', 'lockfront' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th></th><td><strong><?php esc_html_e( 'Input Field', 'lockfront' ); ?></strong></td></tr>
			<?php $this->row_colour( 'tpl_input_bg',     __( 'Input Background', 'lockfront' ), '#f5f5f5' ); ?>
			<?php $this->row_colour( 'tpl_input_border', __( 'Input Border',     'lockfront' ), '#dddddd' ); ?>
			<?php $this->row_colour( 'tpl_input_color',  __( 'Input Text',       'lockfront' ), '#333333' ); ?>
			<?php $this->row_colour( 'tpl_input_focus',  __( 'Focus Ring Color', 'lockfront' ), '#6366f1' ); ?>
			<tr>
				<th scope="row"><label for="lkfr-ir"><?php esc_html_e( 'Input Border Radius', 'lockfront' ); ?></label></th>
				<td>
					<input type="range" id="lkfr-ir" min="0" max="32" step="1" class="lkfr-range"
						name="<?php echo esc_attr( self::OPT_KEY . '[tpl_input_radius]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'tpl_input_radius', '8' ) ); ?>">
					<span class="lkfr-range-val"><?php echo esc_html( lkfr_get( 'tpl_input_radius', '8' ) ); ?></span> px
				</td>
			</tr>
			<tr><th></th><td><strong><?php esc_html_e( 'Button', 'lockfront' ); ?></strong></td></tr>
			<?php $this->row_colour( 'tpl_btn_bg',    __( 'Button Background', 'lockfront' ), '#6366f1' ); ?>
			<?php $this->row_colour( 'tpl_btn_hover', __( 'Button Hover',      'lockfront' ), '#4f46e5' ); ?>
			<?php $this->row_colour( 'tpl_btn_color', __( 'Button Text',       'lockfront' ), '#ffffff' ); ?>
			<tr>
				<th scope="row"><label for="lkfr-br"><?php esc_html_e( 'Button Border Radius', 'lockfront' ); ?></label></th>
				<td>
					<input type="range" id="lkfr-br" min="0" max="32" step="1" class="lkfr-range"
						name="<?php echo esc_attr( self::OPT_KEY . '[tpl_btn_radius]' ); ?>"
						value="<?php echo esc_attr( lkfr_get( 'tpl_btn_radius', '8' ) ); ?>">
					<span class="lkfr-range-val"><?php echo esc_html( lkfr_get( 'tpl_btn_radius', '8' ) ); ?></span> px
				</td>
			</tr>
			<tr><th></th><td><strong><?php esc_html_e( 'Error', 'lockfront' ); ?></strong></td></tr>
			<?php $this->row_colour( 'tpl_error_color', __( 'Error Message Color', 'lockfront' ), '#ef4444' ); ?>
		</table>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Login Logs
	// -----------------------------------------------------------------------

	private function tab_logs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$limit  = 25;
		$offset = ( $paged - 1 ) * $limit;
		$logs   = LKFR_Database::get_logs( array( 'status' => $status, 'limit' => $limit, 'offset' => $offset ) );
		$total  = LKFR_Database::count_logs( $status );
		$pages  = (int) ceil( $total / $limit );
		$base   = admin_url( 'admin.php?page=lockfront&tab=logs' );

		$filters = array(
			''        => __( 'All', 'lockfront' ),
			'success' => __( 'Success', 'lockfront' ),
			'failed'  => __( 'Failed', 'lockfront' ),
			'blocked' => __( 'Blocked', 'lockfront' ),
		);
		?>
		<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
			<div class="subsubsub" style="margin:0;float:none">
				<?php foreach ( $filters as $v => $l ) :
					$url = $v ? add_query_arg( 'status', $v, $base ) : $base;
					$cls = ( $status === $v ) ? 'current' : '';
					printf(
						'<a href="%s" class="%s">%s</a> | ',
						esc_url( $url ),
						esc_attr( $cls ),
						esc_html( $l )
					);
				endforeach; ?>
			</div>
			<button type="button" class="button" id="lkfr-clear-logs">
				<?php esc_html_e( 'Clear All Logs', 'lockfront' ); ?>
			</button>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:155px"><?php esc_html_e( 'Date / Time', 'lockfront' ); ?></th>
					<th style="width:135px"><?php esc_html_e( 'IP Address', 'lockfront' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Status', 'lockfront' ); ?></th>
					<th style="width:150px"><?php esc_html_e( 'Bypass Type', 'lockfront' ); ?></th>
					<th><?php esc_html_e( 'User Agent', 'lockfront' ); ?></th>
				</tr>
			</thead>
			<tbody id="lkfr-logs-tbody">
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="5" style="text-align:center;padding:24px;color:#777">
						<?php esc_html_e( 'No log entries yet.', 'lockfront' ); ?>
					</td>
				</tr>
			<?php else :
				$badge = array(
					'success' => array( '#d1fae5', '#065f46' ),
					'failed'  => array( '#fee2e2', '#991b1b' ),
					'blocked' => array( '#fef3c7', '#92400e' ),
				);
				foreach ( $logs as $row ) :
					$bc = $badge[ $row->status ] ?? array( '#f3f4f6', '#374151' );
			?>
				<tr>
					<td><?php echo esc_html( $row->log_time ); ?></td>
					<td><?php echo esc_html( $row->ip_address ); ?></td>
					<td>
						<span style="background:<?php echo esc_attr( $bc[0] ); ?>;color:<?php echo esc_attr( $bc[1] ); ?>;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap">
							<?php echo esc_html( strtoupper( $row->status ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $row->bypass_type ?: '—' ); ?></td>
					<td>
						<small style="color:#777;word-break:break-all">
							<?php echo esc_html( mb_substr( $row->user_agent, 0, 120 ) ); ?>
						</small>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>

		<?php
		if ( $pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			) );
			echo '</div></div>';
		}
		echo '<p style="color:#999;font-size:12px;margin-top:8px">'
			. sprintf(
				/* translators: %d: total number of log rows */
				esc_html__( 'Total entries: %d', 'lockfront' ),
				(int) $total
			) . '</p>';
	}

	// -----------------------------------------------------------------------
	// Tab: Bypass Tokens
	// -----------------------------------------------------------------------

	private function tab_tokens() {
		$tokens = LKFR_Database::get_tokens();
		$now    = time();
		?>
		<p style="max-width:680px;color:#555;margin-bottom:20px">
			<?php esc_html_e( 'Create time-limited links that bypass the password page. Visitors are NOT logged into WordPress — the protection page is simply skipped.', 'lockfront' ); ?>
		</p>

		<!-- Create form -->
		<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px 24px;max-width:560px;margin-bottom:28px">
			<h3 style="margin-top:0"><?php esc_html_e( 'Create New Bypass Link', 'lockfront' ); ?></h3>
			<table class="form-table" style="margin:0" role="presentation">
				<tr>
					<th style="width:140px;padding-left:0">
						<label for="lkfr-tok-lbl"><?php esc_html_e( 'Label (optional)', 'lockfront' ); ?></label>
					</th>
					<td>
						<input type="text" id="lkfr-tok-lbl" class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. Client Preview', 'lockfront' ); ?>">
					</td>
				</tr>
				<tr>
					<th style="padding-left:0">
						<label for="lkfr-tok-exp"><?php esc_html_e( 'Expires', 'lockfront' ); ?></label>
					</th>
					<td><input type="datetime-local" id="lkfr-tok-exp" class="regular-text"></td>
				</tr>
				<tr>
					<th style="padding-left:0">
						<label for="lkfr-tok-uses"><?php esc_html_e( 'Max Uses', 'lockfront' ); ?></label>
					</th>
					<td>
						<input type="number" id="lkfr-tok-uses" class="small-text" value="0" min="0">
						<p class="description"><?php esc_html_e( '0 = unlimited uses within the expiry window.', 'lockfront' ); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" class="button button-primary" id="lkfr-create-token">
					<?php esc_html_e( '+ Generate Bypass Link', 'lockfront' ); ?>
				</button>
			</p>
			<div id="lkfr-new-result" style="display:none;margin-top:12px;padding:12px 16px;background:#f0fdf4;border:1px solid #86efac;border-radius:4px">
				<p style="font-weight:600;margin:0 0 8px"><?php esc_html_e( '✓ Link created — share this URL:', 'lockfront' ); ?></p>
				<div style="display:flex;gap:8px">
					<input type="text" id="lkfr-new-url" style="flex:1" readonly>
					<button type="button" class="button" id="lkfr-copy-new"><?php esc_html_e( 'Copy', 'lockfront' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Existing tokens table -->
		<h3><?php esc_html_e( 'Existing Links', 'lockfront' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Label', 'lockfront' ); ?></th>
					<th style="width:155px"><?php esc_html_e( 'Created', 'lockfront' ); ?></th>
					<th style="width:155px"><?php esc_html_e( 'Expires', 'lockfront' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Uses', 'lockfront' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Status', 'lockfront' ); ?></th>
					<th style="width:185px"><?php esc_html_e( 'Actions', 'lockfront' ); ?></th>
				</tr>
			</thead>
			<tbody id="lkfr-tokens-tbody">
			<?php if ( empty( $tokens ) ) : ?>
				<tr id="lkfr-no-tokens">
					<td colspan="6" style="text-align:center;padding:24px;color:#777">
						<?php esc_html_e( 'No bypass links yet.', 'lockfront' ); ?>
					</td>
				</tr>
			<?php else :
				foreach ( $tokens as $t ) :
					$expired = strtotime( $t->expires_at ) < $now;
					$maxed   = ( $t->max_uses > 0 && $t->uses >= $t->max_uses );
					$active  = ( ! $expired && ! $maxed );
					$slabel  = $expired ? __( 'Expired', 'lockfront' ) : ( $maxed ? __( 'Max Uses', 'lockfront' ) : __( 'Active', 'lockfront' ) );
					$scol    = $active ? '#16a34a' : '#dc2626';
					$tok_url = add_query_arg( 'lkfr_token', rawurlencode( $t->token ), home_url( '/' ) );
			?>
				<tr id="lkfr-tok-<?php echo absint( $t->id ); ?>">
					<td><?php echo esc_html( $t->label ?: '—' ); ?></td>
					<td style="font-size:12px;color:#666"><?php echo esc_html( $t->created_at ); ?></td>
					<td style="font-size:12px;color:#666"><?php echo esc_html( $t->expires_at ); ?></td>
					<td>
						<?php echo esc_html( $t->uses ); ?>
						<?php if ( $t->max_uses > 0 ) echo esc_html( ' / ' . $t->max_uses ); ?>
					</td>
					<td>
						<span style="color:<?php echo esc_attr( $scol ); ?>;font-weight:700;font-size:12px">
							<?php echo esc_html( $slabel ); ?>
						</span>
					</td>
					<td>
						<button class="button button-small lkfr-copy-tok"
							data-url="<?php echo esc_attr( $tok_url ); ?>">
							<?php esc_html_e( 'Copy URL', 'lockfront' ); ?>
						</button>
						<button class="button button-small lkfr-del-tok"
							data-id="<?php echo absint( $t->id ); ?>"
							style="margin-left:4px;color:#b91c1c;border-color:#fca5a5">
							<?php esc_html_e( 'Delete', 'lockfront' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php
	}

	// -----------------------------------------------------------------------
	// Reusable field helpers
	// -----------------------------------------------------------------------

	/** Toggle switch row. */
	private function row_toggle( $key, $label, $desc = '' ) {
		$val = lkfr_get( $key, '0' );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<input type="hidden"
					name="<?php echo esc_attr( self::OPT_KEY . '[' . $key . ']' ); ?>"
					value="0">
				<label class="lkfr-toggle">
					<input type="checkbox"
						name="<?php echo esc_attr( self::OPT_KEY . '[' . $key . ']' ); ?>"
						value="1"
						<?php checked( '1', $val ); ?>>
					<span class="lkfr-slider"></span>
				</label>
				<?php if ( $desc ) echo '<p class="description">' . esc_html( $desc ) . '</p>'; ?>
			</td>
		</tr>
		<?php
	}

	/** Single-line text field row. */
	private function row_text( $key, $label, $cls = 'regular-text', $desc = '', $placeholder = '' ) {
		?>
		<tr>
			<th scope="row">
				<label for="lkfr-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<input type="text"
					id="lkfr-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( self::OPT_KEY . '[' . $key . ']' ); ?>"
					value="<?php echo esc_attr( lkfr_get( $key ) ); ?>"
					class="<?php echo esc_attr( $cls ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>">
				<?php if ( $desc ) echo '<p class="description">' . esc_html( $desc ) . '</p>'; ?>
			</td>
		</tr>
		<?php
	}

	/** Colour picker row. */
	private function row_colour( $key, $label, $default = '#000000' ) {
		?>
		<tr>
			<th scope="row">
				<label for="lkfr-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<input type="text"
					id="lkfr-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( self::OPT_KEY . '[' . $key . ']' ); ?>"
					value="<?php echo esc_attr( lkfr_get( $key, $default ) ); ?>"
					class="lkfr-color"
					data-default-color="<?php echo esc_attr( $default ); ?>">
			</td>
		</tr>
		<?php
	}

	/** Image upload row. */
	private function row_image( $key, $label, $desc = '' ) {
		$val = lkfr_get( $key );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
					<input type="text"
						id="lkfr-<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( self::OPT_KEY . '[' . $key . ']' ); ?>"
						value="<?php echo esc_attr( $val ); ?>"
						class="regular-text lkfr-img-url"
						readonly
						style="max-width:280px">
					<button type="button" class="button lkfr-img-btn"
						data-target="lkfr-<?php echo esc_attr( $key ); ?>"
						data-preview="lkfr-prev-<?php echo esc_attr( $key ); ?>">
						<?php esc_html_e( 'Select Image', 'lockfront' ); ?>
					</button>
					<button type="button" class="button lkfr-img-rm"
						data-target="lkfr-<?php echo esc_attr( $key ); ?>"
						data-preview="lkfr-prev-<?php echo esc_attr( $key ); ?>"
						<?php echo $val ? '' : 'style="display:none"'; ?>>
						<?php esc_html_e( 'Remove', 'lockfront' ); ?>
					</button>
				</div>
				<img id="lkfr-prev-<?php echo esc_attr( $key ); ?>"
					src="<?php echo $val ? esc_url( $val ) : ''; ?>"
					style="<?php echo $val ? 'display:block;' : 'display:none;'; ?>max-width:200px;max-height:100px;margin-top:8px;border-radius:4px;border:1px solid #ddd">
				<?php if ( $desc ) echo '<p class="description">' . esc_html( $desc ) . '</p>'; ?>
			</td>
		</tr>
		<?php
	}

	// -----------------------------------------------------------------------
	// AJAX handlers
	// -----------------------------------------------------------------------

	/** Create a bypass token. */
	public function ajax_create_token() {
		check_ajax_referer( 'lkfr_bypass_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$label   = isset( $_POST['label'] )      ? sanitize_text_field( wp_unslash( $_POST['label'] ) )      : '';
		$expires = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
		$uses    = isset( $_POST['max_uses'] )   ? absint( $_POST['max_uses'] )                               : 0;

		if ( ! $expires ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Expiry date required.', 'lockfront' ) ) );
		}

		$ts = strtotime( $expires );
		if ( ! $ts || $ts < time() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Expiry date must be in the future.', 'lockfront' ) ) );
		}

		$expires_mysql = gmdate( 'Y-m-d H:i:s', $ts );
		$result        = LKFR_Database::create_token( array(
			'label'      => $label,
			'expires_at' => $expires_mysql,
			'max_uses'   => $uses,
		) );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Database error. Please try again.', 'lockfront' ) ) );
		}

		$url       = add_query_arg( 'lkfr_token', rawurlencode( $result['token'] ), home_url( '/' ) );
		$max_label = $uses > 0 ? '0 / ' . $uses : '0';

		ob_start();
		?>
		<tr id="lkfr-tok-<?php echo absint( $result['id'] ); ?>">
			<td><?php echo esc_html( $label ?: '—' ); ?></td>
			<td style="font-size:12px;color:#666"><?php echo esc_html( current_time( 'mysql' ) ); ?></td>
			<td style="font-size:12px;color:#666"><?php echo esc_html( $expires_mysql ); ?></td>
			<td><?php echo esc_html( $max_label ); ?></td>
			<td><span style="color:#16a34a;font-weight:700;font-size:12px"><?php esc_html_e( 'Active', 'lockfront' ); ?></span></td>
			<td>
				<button class="button button-small lkfr-copy-tok" data-url="<?php echo esc_attr( $url ); ?>">
					<?php esc_html_e( 'Copy URL', 'lockfront' ); ?>
				</button>
				<button class="button button-small lkfr-del-tok" data-id="<?php echo absint( $result['id'] ); ?>"
					style="margin-left:4px;color:#b91c1c;border-color:#fca5a5">
					<?php esc_html_e( 'Delete', 'lockfront' ); ?>
				</button>
			</td>
		</tr>
		<?php
		wp_send_json_success( array( 'url' => $url, 'row' => ob_get_clean() ) );
	}

	/** Delete a bypass token. */
	public function ajax_delete_token() {
		check_ajax_referer( 'lkfr_bypass_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id = isset( $_POST['token_id'] ) ? absint( $_POST['token_id'] ) : 0;
		if ( $id > 0 ) {
			LKFR_Database::delete_token( $id );
		}
		wp_send_json_success();
	}

	/** Truncate the logs table. */
	public function ajax_clear_logs() {
		check_ajax_referer( 'lkfr_clear_logs', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		LKFR_Database::clear_logs();
		wp_send_json_success();
	}

	// -----------------------------------------------------------------------
	// Misc
	// -----------------------------------------------------------------------

	/** Show a warning when protection is enabled but no password is set. */
	public function setup_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'lockfront' ) ) {
			return;
		}
		if ( '1' === lkfr_get( 'enable_protection', '0' ) && ! lkfr_get( 'site_password' ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Lockfront: Protection is enabled but no password is set.', 'lockfront' ),
				esc_url( admin_url( 'admin.php?page=lockfront' ) ),
				esc_html__( 'Set a password →', 'lockfront' )
			);
		}
	}

	/** Add a "Settings" link on the Plugins list page. */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=lockfront' ) ) . '">'
				. esc_html__( 'Settings', 'lockfront' ) . '</a>'
		);
		return $links;
	}

	/** SVG data-URI used as the admin menu icon. */
	private function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
