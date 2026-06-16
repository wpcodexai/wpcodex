<?php
/**
 * Plugin Name:       Worker AI
 * Plugin URI:        https://wpworker.ai
 * Description:       The AI operating system for WordPress developers. Full WordPress control for AI agents — via MCP.
 * Version:           0.5.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            Aminul Islam
 * Author URI:        https://wpworker.ai
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       worker-ai
 * Domain Path:       /languages
 *
 * @package WPWorker
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPWORKER_FILE',        __FILE__ );
// Retrieve the version dynamically from this file's header comments
$plugin_data = get_file_data( WPWORKER_FILE, array( 'Version' => 'Version' ), 'plugin' );
$plugin_version = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.5.0';

define( 'WPWORKER_VERSION',     $plugin_version );
define( 'WPWORKER_DIR',         plugin_dir_path( WPWORKER_FILE ) );
define( 'WPWORKER_URL',         plugin_dir_url( WPWORKER_FILE ) );
define( 'WPWORKER_BASENAME',    plugin_basename( WPWORKER_FILE ) );
define( 'WPWORKER_SANDBOX_DIR', WP_CONTENT_DIR . '/wpworker-sandbox/' );
 
// Autoloader (Jetpack Autoloader — handles wordpress/mcp-adapter)
if ( file_exists( WPWORKER_DIR . 'vendor/autoload_packages.php' ) ) {
	require_once WPWORKER_DIR . 'vendor/autoload_packages.php';
} elseif ( file_exists( WPWORKER_DIR . 'vendor/autoload.php' ) ) {
	// Dev-only fallback (no Jetpack Autoloader installed yet).
	require_once WPWORKER_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function (): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'Worker AI: vendor/ directory not found. Run "composer install" in the plugin directory.',
			'worker-ai'
		);
		echo '</p></div>';
	} );
	return;
}
// Bootstrap

add_action( 'plugins_loaded', function (): void {
	\WPWorker\Plugin::instance()->init();
} );

register_activation_hook( WPWORKER_FILE,   [ \WPWorker\Plugin::class, 'activate' ] );
register_deactivation_hook( WPWORKER_FILE, [ \WPWorker\Plugin::class, 'deactivate' ] );
 