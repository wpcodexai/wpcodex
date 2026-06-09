<?php
/**
 * MCP bootstrap — boots the MCP Adapter, registers ability categories,
 * and applies MCP response filters.
 *
 * Instantiated once from Plugin::init().
 *
 * @package WPCodex\Tools
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Tools;

/**
 * Class Mcp
 *
 * Boots the MCP Adapter, registers wpcodex ability categories,
 * and wires the MCP response filters.
 *
 * @since 1.0.0
 */
class Mcp {

	/**
	 * Wire all MCP hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! $this->boot_mcp_adapter() ) {
			return;
		}
		add_filter( 'mcp_adapter_default_server_config', [ $this, 'configure_mcp_server' ] );
		add_action( 'wp_abilities_api_categories_init',  [ $this, 'register_ability_categories' ] );
		add_filter( 'mcp_adapter_tool_call_result',      [ $this, 'fix_tool_call_result' ], 10, 3 );
		add_filter( 'rest_pre_echo_response',            [ $this, 'fix_json_schema_properties' ] );
	}

	/**
	 * Initialise the bundled MCP Adapter.
	 *
	 * Returns false and queues an admin notice when the adapter class is missing
	 * or throws during initialisation.
	 *
	 * @since 1.0.0
	 * @return bool True when the adapter was initialised successfully.
	 */
	private function boot_mcp_adapter(): bool {
		if ( ! class_exists( \WP\MCP\Core\McpAdapter::class ) ) {
			add_action( 'admin_notices', static function (): void {
				echo '<div class="notice notice-error"><p>';
				esc_html_e(
					'WPCodex: The bundled MCP Adapter could not be loaded. Re-install the plugin release ZIP.',
					'wpcodex'
				);
				echo '</p></div>';
			} );
			return false;
		}

		try {
			\WP\MCP\Core\McpAdapter::instance();
			return true;
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
			return false;
		}
	}

	/**
	 * Brand the MCP server ID, route, and display name.
	 *
	 * Callback for the mcp_adapter_default_server_config filter.
	 *
	 * @since 1.0.0
	 * @param mixed $config Raw server config value from the filter.
	 * @return mixed Updated config array, or the original value if not an array.
	 */
	public function configure_mcp_server( mixed $config ): mixed {
		if ( ! is_array( $config ) ) {
			return $config;
		}
		$config['server_id']    = 'wpcodex';
		$config['server_route'] = 'wpcodex';
		$config['server_name']  = 'WPCodex';
		return $config;
	}

	/**
	 * Register wpcodex ability categories with the WordPress Abilities API.
	 *
	 * Callback for the wp_abilities_api_categories_init action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_ability_categories(): void {
		wp_register_ability_category( 'wpcodex', [
			'label'       => __( 'WPCodex', 'wpcodex' ),
			'description' => __( 'Core WPCodex abilities for AI agent access to WordPress.', 'wpcodex' ),
		] );

		wp_register_ability_category( 'wpcodex-skills', [
			'label'       => __( 'WPCodex Skills', 'wpcodex' ),
			'description' => __( 'Abilities for managing WPCodex skill playbooks.', 'wpcodex' ),
		] );

		wp_register_ability_category( 'wpcodex-gutenberg', [
			'label'       => __( 'WPCodex Gutenberg', 'wpcodex' ),
			'description' => __( 'Abilities for AI-assisted Gutenberg block editing.', 'wpcodex' ),
		] );

		wp_register_ability_category( 'wpcodex-general', [
			'label'       => __( 'WPCodex General', 'wpcodex' ),
			'description' => __( 'General-purpose abilities that may be used by WPCodex or other plugins.', 'wpcodex' ),
		] );
	}

	/**
	 * Unwrap double-wrapped MCP error responses.
	 *
	 * Fixes { success:true, data:{ success:false, error:"…" } } shapes so agents
	 * see real errors instead of a masked success envelope.
	 *
	 * Callback for the mcp_adapter_tool_call_result filter (priority 10, 3 args).
	 *
	 * @since 1.0.0
	 * @param mixed                $result  Raw tool call result.
	 * @param array<string, mixed> $args    Tool call arguments (unused).
	 * @param string               $ability Ability ID being executed.
	 * @return mixed Unwrapped error data, or the original result when not applicable.
	 */
	public function fix_tool_call_result( mixed $result, array $args, string $ability ): mixed {
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
				$data['error'] = $error . "\n\nStructured detail (JSON):\n" . $encoded;
			}
		}
		return $data;
	}

	/**
	 * Replace empty properties arrays with stdClass objects in MCP tool schemas.
	 *
	 * PHP json_encode([]) produces [] but MCP clients expect {} for JSON Schema
	 * properties objects. Fixes all inputSchema and outputSchema entries in the
	 * tools list.
	 *
	 * Callback for the rest_pre_echo_response filter.
	 *
	 * @since 1.0.0
	 * @param mixed $result REST response data.
	 * @return mixed Patched response data, or the original if not applicable.
	 */
	public function fix_json_schema_properties( mixed $result ): mixed {
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
				$tool[ $key ]         = $schema;
			}
		}
		$result_obj->tools = $tools;
		return $result;
	}
}
