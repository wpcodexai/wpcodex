<?php
/**
 * Abstract base class for all WPCodex abilities.
 *
 * Concrete ability classes extend this and implement the abstract methods.
 * The register() method calls wp_register_ability() using get_config() —
 * override register() only when the registration logic must differ (e.g.
 * DiscoverAbilities, which must unregister the MCP Adapter's default first).
 *
 * Pro-plugin extensibility
 * ------------------------
 * A pro plugin adds abilities by filtering 'wpcodex_abilities':
 *
 *   add_filter( 'wpcodex_abilities', function ( array $abilities ): array {
 *       $abilities[] = new \MyProPlugin\Abilities\MyProAbility();
 *       return $abilities;
 *   } );
 *
 * @package WPCodex
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;

use WPCodex\Utils\Helpers;

/**
 * AbstractAbility
 *
 * @since 1.1.0
 */
abstract class AbstractAbility {

	/**
	 * Ability name, e.g. 'wpcodex/file-read'.
	 */
	abstract public function get_name(): string;

	/**
	 * Human-readable label (translatable).
	 */
	abstract public function get_label(): string;

	/**
	 * Short description shown to agents (translatable).
	 */
	abstract public function get_description(): string;

	/**
	 * JSON Schema for the ability's input parameters.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function get_input_schema(): array;

	/**
	 * JSON Schema for the ability's output.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function get_output_schema(): array;

	/**
	 * MCP annotations — return at minimum:
	 *   ['readonly' => bool, 'destructive' => bool, 'idempotent' => bool]
	 *
	 * Do NOT include 'instructions' here; return it from get_instructions() instead.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function get_annotations(): array;

	/**
	 * Execute the ability and return the result.
	 *
	 * @param array<string, mixed> $input Validated input from the MCP caller.
	 * @return array<mixed>|string|\WP_Error
	 */
	abstract public function execute( array $input ): array|string|\WP_Error;

	/**
	 * Ability category slug. Override to use a different category.
	 *
	 * Known categories: 'wpcodex', 'wpcodex-general', 'wpcodex-skills', 'wpcodex-gutenberg'.
	 */
	public function get_category(): string {
		return 'wpcodex';
	}

	/**
	 * Optional agent instructions injected into meta.annotations.instructions.
	 * Return an empty string (default) to omit the instructions key entirely.
	 */
	public function get_instructions(): string {
		return '';
	}

	/**
	 * Permission callback for the ability.
	 *
	 * The return type is bool|\WP_Error so subclasses can return a WP_Error
	 * with a specific HTTP status (e.g. 401/403) when authentication fails.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permission(): bool|\WP_Error {
		return Helpers::ability_permission();
	}

	/**
	 * Register this ability with the WordPress Abilities API.
	 *
	 * Override when the registration logic must differ — for example when the
	 * ability must unregister an existing entry before replacing it.
	 */
	public function register(): void {
		wp_register_ability( $this->get_name(), $this->get_config() );
	}

	/**
	 * Build the full ability configuration array passed to wp_register_ability().
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		$annotations  = $this->get_annotations();
		$instructions = $this->get_instructions();
		if ( '' !== $instructions ) {
			$annotations['instructions'] = $instructions;
		}

		return [
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => $this->get_category(),
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'execute_callback'    => fn( array $input ): array|string|\WP_Error => $this->execute( $input ),
			'permission_callback' => [ $this, 'check_permission' ],
			'meta'                => [
				'annotations' => $annotations,
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		];
	}
}
