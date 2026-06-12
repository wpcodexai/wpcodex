<?php
/**
 * Plugin bootstrap — singleton that wires everything together.
 *
 * @package WPCodex
 */

declare( strict_types=1 );

namespace WPCodex;

use WPCodex\Abilities\Abilities;
use WPCodex\Admin\AbilityPolicy;
use WPCodex\Admin\AdminMenu;
use WPCodex\REST\AdminAccessEndpoint;
use WPCodex\REST\GutenbergFinalizerEndpoint;
use WPCodex\REST\UploadEndpoint;
use WPCodex\Runner\SandboxLoader;
use WPCodex\Skills\BuiltIn;
use WPCodex\Skills\Notices as SkillNotices;
use WPCodex\Skills\Prompts;
use WPCodex\Skills\Schema as SkillsSchema;
use WPCodex\Tools\Mcp;
use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Requirements;

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
	 * @since 1.0.0
	 */
	public function init(): void {
		if ( ! Requirements::check() ) {
			return;
		}

		$this->load_textdomain();
		SkillsSchema::maybe_upgrade();
		new Mcp();
		new AbilityPolicy();
		$this->abilities = new Abilities();
		new UploadEndpoint();
		new AdminAccessEndpoint();
		new GutenbergFinalizerEndpoint();
		new BuiltIn();
		new Prompts();
		new SkillNotices();
		new GutenbergStorage();
		( new SandboxLoader() )->load();

		if ( is_admin() ) {
			AdminMenu::instance();
		}
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
			'wpcodex',
			false,
			dirname( WPCODEX_BASENAME ) . '/languages'
		);
	}

	/**
	 * Creates the PHP execution sandbox directory on activation.
	 *
	 * @since 1.0.0
	 */
	private static function create_sandbox_directory(): void {
		if ( ! is_dir( WPCODEX_SANDBOX_DIR ) ) {
			wp_mkdir_p( WPCODEX_SANDBOX_DIR );
		}

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
