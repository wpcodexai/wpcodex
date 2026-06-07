<?php
/**
 * Plugin bootstrap — singleton that wires everything together.
 *
 * @package WPCodex
 */

declare( strict_types=1 );

namespace WPCodex;

use WPCodex\Admin\AdminMenu;
use WPCodex\Abilities\Abilities;
use WPCodex\Skills\BuiltIn;
use WPCodex\Skills\Prompts;
use WPCodex\Runner\SandboxLoader;
use WPCodex\Skills\Schema  as SkillsSchema;
use WPCodex\Utils\Requirements;

/**
 * Class Plugin
 */
final class Plugin {

	/** @var self|null */
	private static ?self $instance = null;

	/** @var Abilities|null Holds the Abilities instance so it is not GC'd. */
	private ?Abilities $abilities = null;

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
		$this->register_skills_pipeline();
		$this->register_mcp_filters();
		$this->load_sandbox();

		if ( is_admin() ) {
			AdminMenu::instance();
		}
	}

	// Activation / deactivation

	public static function activate(): void {
		SkillsSchema::create_table();
		self::create_sandbox_directory();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	// Private helpers

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
	 * Register wpcodex ability categories.
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
	 * Register abilities — store instance so it is not garbage-collected.
	 */
	private function register_abilities(): void {
		$this->abilities = new Abilities();
	}
	/**
	 * Load enabled sandbox PHP files on every request.
	 */
	private function load_sandbox(): void {
		$sandbox_loader = new SandboxLoader();
		$sandbox_loader->load();
	}
	/**
	 * Wire the skills pipeline: built-in source + prompt abilities.
	 */
	private function register_skills_pipeline(): void {
		BuiltIn::register_source();

		// Register prompt-type abilities for each prompt-enabled skill.
		// Priority 500 — after core abilities (10) and before the collect hook (PHP_INT_MAX).
		add_action( 'wp_abilities_api_init', [ Prompts::class, 'register' ], 500 );
	}

	/**
	 * Register MCP adapter filters that fix two agent-facing issues:
	 *
	 * 1. mcp_adapter_tool_call_result — unwraps { success:true, data:{ success:false } }
	 *    so agents see real errors instead of masked success responses.
	 *
	 * 2. rest_pre_echo_response — replaces empty properties:[] with properties:{}
	 *    in JSON schemas. PHP json_encode([]) produces [] but MCP clients expect {}.
	 */
	private function register_mcp_filters(): void {
		// Filter 1: unwrap double-wrapped error shape.
		add_filter(
			'mcp_adapter_tool_call_result',
			static function ( mixed $result, array $args, string $ability ): mixed {
				if ( $ability !== 'mcp-adapter-execute-ability' ) {
					return $result;
				}
				if ( ! is_array( $result ) || ( $result['success'] ?? null ) !== true ) {
					return $result;
				}
				/** @var array<string, mixed>|null $data */
				$data = $result['data'] ?? null;
				if ( ! is_array( $data ) || ( $data['success'] ?? null ) !== false ) {
					return $result;
				}
				$error = $data['error'] ?? null;
				if ( ! is_string( $error ) || trim( $error ) === '' ) {
					return $result;
				}
				// Append structured detail as JSON suffix so it survives downstream flattening.
				$detail = $data;
				unset( $detail['success'], $detail['error'] );
				if ( $detail !== [] ) {
					$encoded = wp_json_encode( $detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
					if ( is_string( $encoded ) ) {
						$data['error'] = $error . "

Structured detail (JSON):
" . $encoded;
					}				}
				return $data;
			},
			10,
			3
		);

		// Filter 2: fix empty properties:[] → {} in JSON schema output.
		add_filter( 'rest_pre_echo_response', static function ( mixed $result ): mixed {
			if ( ! is_array( $result ) ) {
				return $result;
			}
			/** @var \stdClass|null $result_obj */
			$result_obj = $result['result'] ?? null;
			if ( ! $result_obj instanceof \stdClass ) {
				return $result;
			}
			/** @var list<array<string, mixed>>|null $tools */
			$tools = $result_obj->tools ?? null;
			if ( ! is_array( $tools ) ) {
				return $result;
			}
			foreach ( $tools as &$tool ) {
				foreach ( [ 'inputSchema', 'outputSchema' ] as $key ) {
					/** @var array<string, mixed>|null $schema */
					$schema = $tool[ $key ] ?? null;
					if ( ! is_array( $schema ) || ( $schema['properties'] ?? null ) !== [] ) {
						continue;
					}
					$schema['properties'] = new \stdClass();
					$tool[ $key ] = $schema;
				}
			}
			$result_obj->tools = $tools;
			return $result;
		} );
	}

	/**
	 * Create the PHP execution sandbox directory on activation.
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
