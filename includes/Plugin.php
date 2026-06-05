<?php
/**
 * Plugin bootstrap — singleton that wires everything together.
 *
 * @package WPCodex
 */

declare( strict_types=1 );

namespace WPCodex;

use WPCodex\Admin\AdminMenu;
use WPCodex\Admin\ConnectPage;
use WPCodex\Admin\SettingsPage;
use WPCodex\Abilities\Abilities;
use WPCodex\Skills\Schema  as SkillsSchema;
use WPCodex\Utils\Requirements;

/**
 * Class Plugin
 */
final class Plugin {

	/** @var self|null */
	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialise on plugins_loaded.
	 */
	public function init(): void {
		if ( ! Requirements::check() ) {
			return;
		}

		$this->load_textdomain();
		$this->boot_mcp_adapter();
		$this->register_ability_categories();
		$this->register_abilities();

		if ( is_admin() ) {
			AdminMenu::instance()->register();
			SettingsPage::instance()->register();
			ConnectPage::instance()->register();
		}
	}

	// -------------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------------

	public static function activate(): void {
		SkillsSchema::create_table();
		self::create_sandbox_directory();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcodex',
			false,
			dirname( WPCODEX_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialise the bundled MCP Adapter.
	 */
	private function boot_mcp_adapter(): void {
		if ( ! class_exists( \WP\MCP\Core\McpAdapter::class ) ) {
			add_action( 'admin_notices', static function (): void {
				echo '<div class="notice notice-error"><p>';
				esc_html_e(
					'WPCodex: The bundled MCP Adapter could not be loaded. Re-install the plugin release ZIP.',
					'wpcodex'
				);
				echo '</p></div>';
			} );
			return;
		}

		try {
			\WP\MCP\Core\McpAdapter::instance();

			// Brand our MCP server.
			add_filter( 'mcp_adapter_default_server_config', static function ( mixed $config ): mixed {
				if ( ! is_array( $config ) ) {
					return $config;
				}
				$config['server_id']    = 'wpcodex';
				$config['server_route'] = 'wpcodex';
				$config['server_name']  = 'WPCodex';
				return $config;
			} );

		} catch ( \Throwable $e ) {
			add_action( 'admin_notices', static function () use ( $e ): void {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: %s error message */
					esc_html__( 'WPCodex: MCP Adapter failed to initialise. Error: %s', 'wpcodex' ),
					esc_html( $e->getMessage() )
				);
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Register wpcodex ability categories on wp_abilities_api_categories_init.
	 */
	private function register_ability_categories(): void {
		add_action( 'wp_abilities_api_categories_init', static function (): void {
			wp_register_ability_category( 'wpcodex', [
				'label'       => __( 'WPCodex', 'wpcodex' ),
				'description' => __( 'Core WPCodex abilities for AI agent access to WordPress.', 'wpcodex' ),
			] );

			wp_register_ability_category( 'wpcodex-skills', [
				'label'       => __( 'WPCodex Skills', 'wpcodex' ),
				'description' => __( 'Abilities for managing WPCodex skill playbooks.', 'wpcodex' ),
			] );
		} );
	}

	/**
	 * Require and register all ability classes on wp_abilities_api_init.
	 */
	private function register_abilities(): void {
		new Abilities();
	}

	/**
	 * Create the PHP execution sandbox directory on activation.
	 */
	private static function create_sandbox_directory(): void {
		if ( ! is_dir( WPCODEX_SANDBOX_DIR ) ) {
			wp_mkdir_p( WPCODEX_SANDBOX_DIR );
		}

		// Block HTTP access.
		$htaccess = WPCODEX_SANDBOX_DIR . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		$index = WPCODEX_SANDBOX_DIR . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}