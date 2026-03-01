<?php
/**
 * Database management for Lockfront.
 *
 * @package Lockfront
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles creation and querying of all Lockfront database tables.
 */
class LKFR_Database {

	// Table name suffixes (without $wpdb->prefix).
	const LOGS_TABLE     = 'lkfr_login_logs';
	const TOKENS_TABLE   = 'lkfr_bypass_tokens';
	const ATTEMPTS_TABLE = 'lkfr_login_attempts';

	// -----------------------------------------------------------------------
	// Schema
	// -----------------------------------------------------------------------

	/**
	 * Create or upgrade all plugin tables using dbDelta().
	 */
	public static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$tables = array(

			"CREATE TABLE {$wpdb->prefix}lkfr_login_logs (
				id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				log_time    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				ip_address  VARCHAR(100)        NOT NULL DEFAULT '',
				user_agent  TEXT                NOT NULL,
				status      VARCHAR(20)         NOT NULL DEFAULT 'success',
				bypass_type VARCHAR(50)         NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY log_time  (log_time),
				KEY status    (status)
			) {$charset};",

			"CREATE TABLE {$wpdb->prefix}lkfr_bypass_tokens (
				id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				token      VARCHAR(128)        NOT NULL DEFAULT '',
				expires_at DATETIME            NOT NULL,
				label      VARCHAR(255)        NOT NULL DEFAULT '',
				uses       INT(11)             NOT NULL DEFAULT 0,
				max_uses   INT(11)             NOT NULL DEFAULT 0,
				created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY token      (token),
				KEY        expires_at (expires_at)
			) {$charset};",

			"CREATE TABLE {$wpdb->prefix}lkfr_login_attempts (
				id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				ip_address VARCHAR(100)        NOT NULL DEFAULT '',
				attempt_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY ip_address (ip_address),
				KEY attempt_at (attempt_at)
			) {$charset};",
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'lkfr_db_version', LKFR_DB_VERSION );
	}

	// -----------------------------------------------------------------------
	// Login logs
	// -----------------------------------------------------------------------

	/**
	 * Record a login attempt result.
	 *
	 * @param string $status      'success' | 'failed' | 'blocked'.
	 * @param string $bypass_type Optional bypass method label.
	 */
	public static function log_login( $status = 'success', $bypass_type = '' ) {
		global $wpdb;

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . self::LOGS_TABLE,
			array(
				'log_time'    => current_time( 'mysql' ),
				'ip_address'  => self::client_ip(),
				'user_agent'  => $ua,
				'status'      => sanitize_text_field( $status ),
				'bypass_type' => sanitize_text_field( $bypass_type ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Fetch log rows.
	 *
	 * @param array $args limit, offset, status, order.
	 * @return array
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'limit'  => 25,
				'offset' => 0,
				'status' => '',
				'order'  => 'DESC',
			)
		);

		$table = $wpdb->prefix . self::LOGS_TABLE;
		$order = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';
		$limit = (int) $args['limit'];
		$off   = (int) $args['offset'];

		// Build queries inline so the plugin checker sees a fully-prepared call.
		if ( ! empty( $args['status'] ) ) {
			if ( 'ASC' === $order ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY log_time ASC LIMIT %d OFFSET %d", $args['status'], $limit, $off ) ) ?: array();
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY log_time DESC LIMIT %d OFFSET %d", $args['status'], $limit, $off ) ) ?: array();
		}

		if ( 'ASC' === $order ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY log_time ASC LIMIT %d OFFSET %d", $limit, $off ) ) ?: array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY log_time DESC LIMIT %d OFFSET %d", $limit, $off ) ) ?: array();
	}

	/**
	 * Count log rows, optionally filtered by status.
	 *
	 * @param string $status Optional status filter.
	 * @return int
	 */
	public static function count_logs( $status = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . self::LOGS_TABLE;

		if ( ! empty( $status ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/** Truncate all log rows. */
	public static function clear_logs() {
		global $wpdb;
		$logs_table = $wpdb->prefix . self::LOGS_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "TRUNCATE TABLE {$logs_table}" );
	}

	// -----------------------------------------------------------------------
	// Brute-force attempts
	// -----------------------------------------------------------------------

	/**
	 * Record a single failed attempt for the given IP.
	 *
	 * @param string $ip Validated IP address.
	 */
	public static function record_attempt( $ip ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . self::ATTEMPTS_TABLE,
			array(
				'ip_address' => sanitize_text_field( $ip ),
				'attempt_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Count recent attempts from an IP within a rolling window.
	 *
	 * @param string $ip      IP address.
	 * @param int    $minutes Window size in minutes.
	 * @return int
	 */
	public static function count_attempts( $ip, $minutes = 15 ) {
		global $wpdb;
		$table     = $wpdb->prefix . self::ATTEMPTS_TABLE;
		$threshold = gmdate( 'Y-m-d H:i:s', time() - absint( $minutes ) * 60 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ip_address = %s AND attempt_at > %s",
				$ip,
				$threshold
			)
		);
	}

	// -----------------------------------------------------------------------
	// Bypass tokens
	// -----------------------------------------------------------------------

	/**
	 * Return all tokens ordered by creation date.
	 *
	 * @return array
	 */
	public static function get_tokens() {
		global $wpdb;
		$table = $wpdb->prefix . self::TOKENS_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" ) ?: array();
	}

	/**
	 * Fetch a single token row by its token string.
	 *
	 * @param string $token Raw token string.
	 * @return object|null
	 */
	public static function get_token( $token ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TOKENS_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) );
	}

	/**
	 * Insert a new bypass token.
	 *
	 * @param array $data label, expires_at (MySQL datetime), max_uses.
	 * @return array|false Array with 'id' and 'token', or false on failure.
	 */
	public static function create_token( $data ) {
		global $wpdb;

		$token = bin2hex( random_bytes( 32 ) );

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . self::TOKENS_TABLE,
			array(
				'token'      => $token,
				'expires_at' => $data['expires_at'],
				'label'      => sanitize_text_field( $data['label'] ),
				'max_uses'   => absint( $data['max_uses'] ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		return array( 'id' => $wpdb->insert_id, 'token' => $token );
	}

	/**
	 * Increment the use counter for a token.
	 *
	 * @param int $id Token row ID.
	 */
	public static function increment_token( $id ) {
		global $wpdb;
		$tokens_table = $wpdb->prefix . self::TOKENS_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tokens_table} SET uses = uses + 1 WHERE id = %d",
				absint( $id )
			)
		);
	}

	/**
	 * Delete a token by ID.
	 *
	 * @param int $id Token row ID.
	 */
	public static function delete_token( $id ) {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . self::TOKENS_TABLE,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	// -----------------------------------------------------------------------
	// Utility
	// -----------------------------------------------------------------------

	/**
	 * Determine the real client IP, respecting common proxy headers.
	 *
	 * @return string Validated IP or '0.0.0.0'.
	 */
	public static function client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}
}
