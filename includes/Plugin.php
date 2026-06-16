<?php
/**
 * Plugin bootstrap — singleton that wires everything together.
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker;

use WPWorker\Abilities\Abilities;
use WPWorker\Admin\AbilityPolicy;
use WPWorker\Admin\AdminMenu;
use WPWorker\REST\AdminAccessEndpoint;
use WPWorker\REST\GutenbergFinalizerEndpoint;
use WPWorker\REST\UploadEndpoint;
use WPWorker\Runner\SandboxLoader;
use WPWorker\Skills\BuiltIn;
use WPWorker\Skills\Notices as SkillNotices;
use WPWorker\Skills\Prompts;
use WPWorker\Skills\Schema as SkillsSchema;
use WPWorker\Tools\Mcp;
use WPWorker\Utils\GutenbergStorage;
use WPWorker\Utils\Requirements;

/**
 * Class Plugin
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   self|null
	 */
	private static ?self $instance = null;

	/**
	 * Holds the Abilities instance so it is not garbage-collected.
	 *
	 * @since 1.0.0
	 * @var   Abilities|null
	 */
	private ?Abilities $abilities = null;

	private function __construct() {}

	/**
	 * Return (and lazily create) the singleton instance.
	 *
	 * @since 1.0.0
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialises the plugin on plugins_loaded.
	 *
	 * The MCP server, abilities, and all REST endpoints are only booted when
	 * the site owner has explicitly added:
	 *
	 *   define( 'WP_WORKER_ENABLE_MCP', true );
	 *
	 * to their wp-config.php. Without this constant the plugin is inert —
	 * it runs no MCP transport, registers no abilities, and exposes no
	 * REST endpoints. The admin UI and Skills system remain available so
	 * the owner can manage skills and view setup instructions.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		if ( ! Requirements::check() ) {
			return;
		}

		$this->load_textdomain();
		SkillsSchema::maybe_upgrade();
		new BuiltIn();
		new Prompts();
		new SkillNotices();

		if ( defined( 'WP_WORKER_ENABLE_MCP' ) && WP_WORKER_ENABLE_MCP ) {
			new Mcp();
			new AbilityPolicy();
			$this->abilities = new Abilities();
			new UploadEndpoint();
			new AdminAccessEndpoint();
			new GutenbergFinalizerEndpoint();
			new GutenbergStorage();
			( new SandboxLoader() )->load();
		} else {
			add_action( 'admin_notices', [ $this, 'add_mcp_disabled_notice' ] );
		}

		if ( is_admin() ) {
			AdminMenu::instance();
		}
	}

	/**
	 * Queues an admin notice shown when WP_WORKER_ENABLE_MCP is not defined.
	 *
	 * Explains to the site administrator exactly what constant to add to
	 * wp-config.php in order to activate the MCP server.
	 *
	 * @since 1.0.0
	 */
	public function add_mcp_disabled_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_wpworker' !== $screen->id ) {
			return;
		}

		echo '<div class="notice notice-info">';
		echo '<p><strong>';
		esc_html_e( 'Worker AI — MCP server is disabled (safe by default).', 'worker-ai' );
		echo '</strong></p><p>';
		esc_html_e(
			'To enable the MCP server and give your AI agent access to this site, add the following line to your wp-config.php:',
			'worker-ai'
		);
		echo '</p>';
		echo '<pre style="background:#f6f7f7;border:1px solid #dcdcde;padding:8px 14px;display:inline-block;border-radius:3px;font-size:13px;">';
		echo "define( 'WP_WORKER_ENABLE_MCP', true );";
		echo '</pre>';
		echo '<p>';
		echo wp_kses(
			sprintf(
				/* translators: %s: documentation URL */
				__(
					'This constant must be set intentionally — it activates PHP execution, filesystem access, database queries, and WP-CLI for authenticated AI clients. Only enable it on development or staging sites. ',
					'worker-ai'
				),
				//<a href="%s" target="_blank" rel="noopener noreferrer">Read the documentation →</a>
				//esc_url( 'https://wpworker.ai/docs/getting-started' )
			),
			[
				'a' => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
			]
		);
		echo '</p></div>';
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate(): void {
		SkillsSchema::create_table();
		self::create_sandbox_directory();
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @since 1.0.0
	 */
	private function load_textdomain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- load_plugin_textdomain is correct for manually bundled/GlotPress translations
		load_plugin_textdomain(
			'worker-ai',
			false,
			dirname( WPWORKER_BASENAME ) . '/languages'
		);
	}

	/**
	 * Creates the PHP execution sandbox directory on activation.
	 *
	 * @since 1.0.0
	 */
	private static function create_sandbox_directory(): void {
		if ( ! is_dir( WPWORKER_SANDBOX_DIR ) ) {
			wp_mkdir_p( WPWORKER_SANDBOX_DIR );
		}

		$htaccess = WPWORKER_SANDBOX_DIR . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		$index = WPWORKER_SANDBOX_DIR . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}
