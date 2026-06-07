<?php
/**
 * Plugin Name:       WPCodex
 * Plugin URI:        https://github.com/wpcodex/wpcodex
 * Description:       The AI operating system for WordPress developers. Full WordPress control for AI agents — via MCP.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.0
 * Author:            WPCodex Team
 * Author URI:        https://wpcodex.ai
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wpcodex
 * Domain Path:       /languages
 *
 * @package WPCodex
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPCODEX_FILE',        __FILE__ );
// Retrieve the version dynamically from this file's header comments
$plugin_data = get_file_data( WPCODEX_FILE, array( 'Version' => 'Version' ), 'plugin' );
$plugin_version = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '1.0.0';

define( 'WPCODEX_VERSION',     $plugin_version );
define( 'WPCODEX_DIR',         plugin_dir_path( WPCODEX_FILE ) );
define( 'WPCODEX_URL',         plugin_dir_url( WPCODEX_FILE ) );
define( 'WPCODEX_BASENAME',    plugin_basename( WPCODEX_FILE ) );
define( 'WPCODEX_SANDBOX_DIR', WP_CONTENT_DIR . '/wpcodex-sandbox/' );
 
// Autoloader (Jetpack Autoloader — handles wordpress/mcp-adapter)
if ( file_exists( WPCODEX_DIR . 'vendor/autoload_packages.php' ) ) {
	require_once WPCODEX_DIR . 'vendor/autoload_packages.php';
} elseif ( file_exists( WPCODEX_DIR . 'vendor/autoload.php' ) ) {
	// Dev-only fallback (no Jetpack Autoloader installed yet).
	require_once WPCODEX_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', static function (): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'WPCodex: vendor/ directory not found. Run "composer install" in the plugin directory.',
			'wpcodex'
		);
		echo '</p></div>';
	} );
	return;
}
// Bootstrap
 
add_action( 'plugins_loaded', static function (): void {
	\WPCodex\Plugin::instance()->init();
} );
 
register_activation_hook( WPCODEX_FILE,   [ \WPCodex\Plugin::class, 'activate' ] );
register_deactivation_hook( WPCODEX_FILE, [ \WPCodex\Plugin::class, 'deactivate' ] );
 