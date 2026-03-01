<?php
/**
 * Bypass URL key and temporary token access.
 *
 * @package Lockfront
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the two bypass mechanisms: permanent URL key and time-limited tokens.
 */
class LKFR_Bypass {

	/**
	 * Run both bypass checks.
	 *
	 * @return bool True if access should be granted.
	 */
	public function check() {
		return $this->check_url_key() || $this->check_token();
	}

	// -----------------------------------------------------------------------
	// URL key bypass (permanent)
	// -----------------------------------------------------------------------

	/**
	 * Grant access when the URL contains the configured key=value pair.
	 * A short-lived session cookie is set so the visitor doesn't need to
	 * append the key on every page navigation.
	 *
	 * @return bool
	 */
	private function check_url_key() {
		$key = lkfr_get( 'bypass_key',   '' );
		$val = lkfr_get( 'bypass_value', '' );

		if ( ! $key || ! $val ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ $key ] ) ) {
			$supplied = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			if ( hash_equals( $val, $supplied ) ) {
				$this->set_bypass_cookie( 'key' );
				LKFR_Database::log_login( 'success', 'url_key_bypass' );
				return true;
			}
		}

		return $this->has_bypass_cookie( 'key' );
	}

	// -----------------------------------------------------------------------
	// Token bypass (temporary, DB-backed)
	// -----------------------------------------------------------------------

	/**
	 * Grant access when the URL contains a valid lkfr_token parameter.
	 *
	 * @return bool
	 */
	private function check_token() {
		// The token itself IS the security credential — nonce verification is
		// intentionally not used here. The 64-char random hex token replaces it.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['lkfr_token'] ) ) {
			return $this->has_bypass_cookie( 'token' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = sanitize_text_field( wp_unslash( $_GET['lkfr_token'] ) );
		$row = LKFR_Database::get_token( $raw );

		if ( ! $row ) {
			return false;
		}

		// Expired?
		if ( strtotime( $row->expires_at ) < time() ) {
			return false;
		}

		// Max uses reached?
		if ( $row->max_uses > 0 && $row->uses >= $row->max_uses ) {
			return false;
		}

		LKFR_Database::increment_token( $row->id );
		$this->set_bypass_cookie( 'token' );
		LKFR_Database::log_login( 'success', 'token_bypass' );

		return true;
	}

	// -----------------------------------------------------------------------
	// Bypass cookies (8-hour, prevents re-appending key on every page load)
	// -----------------------------------------------------------------------

	/**
	 * Cookie name for a given bypass type.
	 *
	 * @param string $type 'key' | 'token'.
	 * @return string
	 */
	private function cookie_name( $type ) {
		return 'lkfr_bypass_' . sanitize_key( $type );
	}

	/**
	 * HMAC value stored in the bypass cookie.
	 *
	 * @param string $type Bypass type.
	 * @return string
	 */
	private function cookie_value( $type ) {
		return hash_hmac( 'sha256', $type . LKFR_DB_VERSION, wp_salt( 'auth' ) );
	}

	/**
	 * Set an 8-hour bypass cookie and make it available to the current request.
	 *
	 * @param string $type Bypass type.
	 */
	private function set_bypass_cookie( $type ) {
		$name  = $this->cookie_name( $type );
		$value = $this->cookie_value( $type );

		setcookie(
			$name,
			$value,
			array(
				'expires'  => time() + 8 * HOUR_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Make it available within the same request.
		$_COOKIE[ $name ] = $value;
	}

	/**
	 * Check whether a valid bypass cookie of the given type exists.
	 *
	 * @param string $type Bypass type.
	 * @return bool
	 */
	private function has_bypass_cookie( $type ) {
		$name = $this->cookie_name( $type );
		if ( empty( $_COOKIE[ $name ] ) ) {
			return false;
		}
		return hash_equals(
			$this->cookie_value( $type ),
			sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) )
		);
	}
}
