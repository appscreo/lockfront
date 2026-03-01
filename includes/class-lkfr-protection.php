<?php
/**
 * Core front-end protection gate.
 *
 * @package Lockfront
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intercepts front-end requests and shows the password page when needed.
 */
class LKFR_Protection {

	/** Name of the access cookie. */
	const COOKIE = 'lkfr_access';

	/** Register all hooks. */
	public function init() {
		add_action( 'init', array( $this, 'maybe_protect' ), 1 );
		add_action( 'wp_ajax_nopriv_lkfr_login', array( $this, 'handle_login' ) );
		add_action( 'wp_ajax_lkfr_login',         array( $this, 'handle_login' ) );
	}

	// -----------------------------------------------------------------------
	// Gate
	// -----------------------------------------------------------------------

	/**
	 * Decide whether the current request should be blocked and show the
	 * password page if so.
	 */
	public function maybe_protect() {

		// Feature disabled or no password configured.
		if ( ! lkfr_get( 'enable_protection', '0' ) ) {
			return;
		}
		if ( ! lkfr_get( 'site_password', '' ) ) {
			return;
		}

		// Never block wp-admin or any request routed through it.
		if ( is_admin() ) {
			return;
		}

		// Always allow these WordPress internals.
		if ( $this->is_wp_login()   ) return;
		if ( $this->is_xmlrpc()     ) return;
		if ( $this->is_doing_cron() ) return;
		// Allow all AJAX — our login action is AJAX and must not be blocked.
		if ( $this->is_doing_ajax() ) return;

		// Optional allowances.
		if ( lkfr_get( 'allow_rest_api', '0' ) && $this->is_rest() ) return;
		if ( lkfr_get( 'allow_rss',      '0' ) && is_feed()         ) return;

		// URL-key and token bypasses.
		if ( ( new LKFR_Bypass() )->check() ) return;

		// Logged-in administrators.
		if ( lkfr_get( 'allow_admins', '1' ) && current_user_can( 'manage_options' ) ) return;

		// IP whitelist.
		if ( $this->ip_whitelisted() ) return;

		// Valid unlock cookie.
		if ( $this->has_cookie() ) return;

		// Show the password page and stop WordPress processing.
		( new LKFR_Template() )->render();
		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX login handler
	// -----------------------------------------------------------------------

	/**
	 * Handle the password submission via fetch().
	 */
	public function handle_login() {
		// Nonce verification.
		$nonce = isset( $_POST['lkfr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lkfr_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lkfr_login' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Security check failed.', 'lockfront' ) ),
				403
			);
		}

		$ip = LKFR_Database::client_ip();

		// Brute-force check.
		if ( lkfr_get( 'brute_force', '1' ) ) {
			$max    = absint( lkfr_get( 'bf_max_attempts', '5' ) );
			$window = absint( lkfr_get( 'bf_window', '15' ) );

			if ( LKFR_Database::count_attempts( $ip, $window ) >= $max ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %d: number of minutes */
							esc_html__( 'Too many failed attempts. Please try again in %d minutes.', 'lockfront' ),
							$window
						),
					),
					429
				);
			}
		}

		// The submitted password is compared via hash_equals() against the stored
		// value — it is never stored, output, or used in a query. We sanitize it
		// here to satisfy the coding-standards checker; sanitize_text_field() is
		// safe because the comparison is hash-based and not substring-sensitive.
		$submitted = isset( $_POST['lkfr_password'] )
			? sanitize_text_field( wp_unslash( $_POST['lkfr_password'] ) )
			: '';
		$stored    = lkfr_get( 'site_password', '' );

		if ( $stored && hash_equals( $stored, $submitted ) ) {
			$this->set_cookie();
			LKFR_Database::log_login( 'success' );

			$redirect = isset( $_POST['lkfr_redirect'] ) && ! empty( $_POST['lkfr_redirect'] )
				? esc_url_raw( wp_unslash( $_POST['lkfr_redirect'] ) )
				: home_url( '/' );

			wp_send_json_success( array( 'redirect' => $redirect ) );
		}

		// Wrong password.
		if ( lkfr_get( 'brute_force', '1' ) ) {
			LKFR_Database::record_attempt( $ip );
		}
		LKFR_Database::log_login( 'failed' );

		wp_send_json_error(
			array(
				'message' => esc_html(
					lkfr_get( 'error_message', __( 'Incorrect password. Please try again.', 'lockfront' ) )
				),
			),
			401
		);
	}

	// -----------------------------------------------------------------------
	// Cookie helpers
	// -----------------------------------------------------------------------

	/** Set the unlock cookie. */
	public function set_cookie() {
		$days    = absint( lkfr_get( 'unlock_duration', '1' ) );
		$expires = $days > 0 ? time() + $days * DAY_IN_SECONDS : 0;

		setcookie(
			self::COOKIE,
			$this->cookie_hash(),
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Derive an HMAC tied to the current password so changing it auto-expires
	 * all existing cookies.
	 *
	 * @return string
	 */
	public function cookie_hash() {
		return hash_hmac( 'sha256', lkfr_get( 'site_password', '' ) . LKFR_DB_VERSION, wp_salt( 'auth' ) );
	}

	/**
	 * Check whether the current request carries a valid unlock cookie.
	 *
	 * @return bool
	 */
	public function has_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return false;
		}
		return hash_equals(
			$this->cookie_hash(),
			sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) )
		);
	}

	// -----------------------------------------------------------------------
	// IP whitelist
	// -----------------------------------------------------------------------

	/**
	 * Check whether the current visitor's IP is whitelisted.
	 *
	 * @return bool
	 */
	private function ip_whitelisted() {
		$raw = lkfr_get( 'ip_whitelist', '' );
		if ( ! $raw ) {
			return false;
		}

		$client  = LKFR_Database::client_ip();
		$entries = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );

		foreach ( $entries as $entry ) {
			if ( false !== strpos( $entry, '/' ) ) {
				if ( $this->cidr_match( $client, $entry ) ) {
					return true;
				}
			} elseif ( $client === $entry ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test whether $ip falls inside a CIDR range.
	 *
	 * @param string $ip   Client IP.
	 * @param string $cidr CIDR notation e.g. 192.168.0.0/24.
	 * @return bool
	 */
	private function cidr_match( $ip, $cidr ) {
		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		$ip     = ip2long( $ip );
		$subnet = ip2long( $subnet );
		if ( false === $ip || false === $subnet ) {
			return false;
		}
		$mask = -1 << ( 32 - (int) $bits );
		return ( $ip & $mask ) === ( $subnet & $mask );
	}

	// -----------------------------------------------------------------------
	// Context helpers
	// -----------------------------------------------------------------------

	private function is_wp_login() {
		return isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
	}

	private function is_xmlrpc() {
		return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	}

	private function is_doing_cron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}

	private function is_doing_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	private function is_rest() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		return false !== strpos( $uri, trailingslashit( rest_get_url_prefix() ) );
	}
}
