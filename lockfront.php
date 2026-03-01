<?php
/**
 * Plugin Name:       Lockfront
 * Plugin URI:        https://wordpress.org/plugins/lockfront/
 * Description:       Password-protect your WordPress site while it's under development. Includes brute-force protection, login logs, IP whitelisting, bypass URLs, temporary access links, and a fully customisable front door.
 * Version:           1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AppsCreo
 * Author URI:        https://appscreo.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lockfront
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------
define( 'LKFR_VERSION',     '1.0' );
define( 'LKFR_PLUGIN_FILE', __FILE__ );
define( 'LKFR_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'LKFR_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'LKFR_DB_VERSION',  '1.0' );
define( 'LKFR_OPTION_KEY',  'lkfr_settings' );

// -----------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------
require_once LKFR_PLUGIN_DIR . 'includes/class-lkfr-database.php';
require_once LKFR_PLUGIN_DIR . 'includes/class-lkfr-protection.php';
require_once LKFR_PLUGIN_DIR . 'includes/class-lkfr-bypass.php';
require_once LKFR_PLUGIN_DIR . 'includes/class-lkfr-template.php';
require_once LKFR_PLUGIN_DIR . 'includes/class-lkfr-admin.php';

// -----------------------------------------------------------------------
// Settings helper
// -----------------------------------------------------------------------

/**
 * Retrieve a single Lockfront setting with an optional default.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value returned when the key is absent or empty string.
 * @return mixed
 */
function lkfr_get( $key, $default = '' ) {
	static $cache = null;
	if ( null === $cache ) {
		$cache = get_option( LKFR_OPTION_KEY, array() );
	}
	// Treat empty-string as "not set" so defaults kick in properly.
	return ( isset( $cache[ $key ] ) && '' !== $cache[ $key ] ) ? $cache[ $key ] : $default;
}

// -----------------------------------------------------------------------
// Activation / deactivation
// -----------------------------------------------------------------------
register_activation_hook( LKFR_PLUGIN_FILE, array( 'LKFR_Database', 'create_tables' ) );

register_deactivation_hook( LKFR_PLUGIN_FILE, 'lkfr_on_deactivate' );
/**
 * Clean up transients on deactivation.
 */
function lkfr_on_deactivate() {
	delete_transient( 'lkfr_cache' );
}

// -----------------------------------------------------------------------
// Boot
// -----------------------------------------------------------------------
add_action( 'plugins_loaded', 'lkfr_boot' );
/**
 * Initialise the plugin after all plugins are loaded.
 */
function lkfr_boot() {
	// Translations are loaded automatically by WordPress since 4.6 when the
	// plugin is hosted on WordPress.org — no manual call required.

	// Run dbDelta if the schema version has changed.
	if ( get_option( 'lkfr_db_version' ) !== LKFR_DB_VERSION ) {
		LKFR_Database::create_tables();
	}

	( new LKFR_Protection() )->init();

	if ( is_admin() ) {
		new LKFR_Admin();
	}
}
