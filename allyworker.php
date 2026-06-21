<?php
/**
 * Plugin Name:       AllyWorker
 * Plugin URI:        https://allyworker.com
 * Description:       The AI operating system for WordPress developers. Full WordPress control for AI agents — via MCP.
 * Version:           0.5.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            AllyWorker Team
 * Author URI:        https://allyworker.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       allyworker
 * Domain Path:       /languages
 *
 * @package AllyWorker
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ALLY_WORKER_FILE',        __FILE__ );
// Retrieve the version dynamically from this file's header comments
$plugin_data = get_file_data( ALLY_WORKER_FILE, array( 'Version' => 'Version' ), 'plugin' );
$plugin_version = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.5.0';

define( 'ALLY_WORKER_VERSION',     $plugin_version );
define( 'ALLY_WORKER_DIR',         plugin_dir_path( ALLY_WORKER_FILE ) );
define( 'ALLY_WORKER_URL',         plugin_dir_url( ALLY_WORKER_FILE ) );
define( 'ALLY_WORKER_BASENAME',    plugin_basename( ALLY_WORKER_FILE ) );
define( 'ALLY_WORKER_SANDBOX_DIR', WP_CONTENT_DIR . '/wp-allyworker-sandbox/' );
 
// Autoloader (Jetpack Autoloader — handles wordpress/mcp-adapter)
if ( file_exists( ALLY_WORKER_DIR . 'vendor/autoload_packages.php' ) ) {
	require_once ALLY_WORKER_DIR . 'vendor/autoload_packages.php';
} elseif ( file_exists( ALLY_WORKER_DIR . 'vendor/autoload.php' ) ) {
	// Dev-only fallback (no Jetpack Autoloader installed yet).
	require_once ALLY_WORKER_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function (): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'AllyWorker: vendor/ directory not found. Run "composer install" in the plugin directory.',
			'allyworker'
		);
		echo '</p></div>';
	} );
	return;
}
// Bootstrap

add_action( 'plugins_loaded', function (): void {
	\AllyWorker\Plugin::instance()->init();
} );

register_activation_hook( ALLY_WORKER_FILE,   [ \AllyWorker\Plugin::class, 'activate' ] );
register_deactivation_hook( ALLY_WORKER_FILE, [ \AllyWorker\Plugin::class, 'deactivate' ] );
 