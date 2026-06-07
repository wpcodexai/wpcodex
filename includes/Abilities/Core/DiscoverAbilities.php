<?php
/**
 * Ability: wpcodex/discover-abilities
 *
 * Replaces the MCP Adapter's built-in mcp-adapter/discover-abilities so the
 * response includes WPCodex environment instructions and the skill catalog.
 * Agents read this at session start and immediately know what tools and skills
 * are available without a separate tool call.
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Core;

use WPCodex\Skills\Repository;
use WPCodex\Skills\Parser;
use WPCodex\Utils\Helpers;

class DiscoverAbilities {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }

	public function init(): void {
		// wp_get_ability( 'name' ) calls WP_Abilities_Registry::get_registered()
		// which triggers _doing_it_wrong() in WP 6.9 when the ability doesn't exist.
		// The safe way to check existence is via wp_get_abilities() — it returns
		// the full registry array without any per-key error triggering.
		$registered = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : [];

		// Unregister the MCP Adapter's default so we can replace it.
		if ( isset( $registered['mcp-adapter/discover-abilities'] )
			&& function_exists( 'wp_unregister_ability' )
		) {
			wp_unregister_ability( 'mcp-adapter/discover-abilities' );
		}

		// Guard: if unregister failed (locked ability), don't double-register.
		// Re-fetch after unregister to get the current state.
		$registered_after = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : [];
		if ( isset( $registered_after['mcp-adapter/discover-abilities'] ) ) {
			return;
		}

		wp_register_ability( 'mcp-adapter/discover-abilities', [
			'label'       => __( 'Discover Abilities', 'wpcodex' ),
			'description' => __(
				'Discover all available WordPress abilities. Returns the full ability list plus WPCodex environment instructions and skill catalog. Call this at the start of every session.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'instructions' => [
						'type'        => 'string',
						'description' => 'WPCodex environment instructions and skill catalog for this session.',
					],
					'abilities'    => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'name'        => [ 'type' => 'string' ],
								'label'       => [ 'type' => 'string' ],
								'description' => [ 'type' => 'string' ],
							],
							'required' => [ 'name', 'label', 'description' ],
						],
					],
				],
				'required' => [ 'instructions', 'abilities' ],
			],

			'execute_callback' => static function (): array {
				// Build ability list — only public tool-type abilities.
				$ability_list = [];
				if ( function_exists( 'wp_get_abilities' ) ) {
					foreach ( wp_get_abilities() as $ability ) {
						if ( ! ( $ability instanceof \WP_Ability ) ) {
							continue;
						}
						$meta = $ability->get_meta();
						if ( ! ( $meta['mcp']['public'] ?? false ) ) {
							continue;
						}
						if ( ( $meta['mcp']['type'] ?? 'tool' ) !== 'tool' ) {
							continue;
						}
						$ability_list[] = [
							'name'        => $ability->get_name(),
							'label'       => $ability->get_label(),
							'description' => $ability->get_description(),
						];
					}
				}

				// Build instructions with skill catalog.
				$instructions = self::build_instructions();

				return [
					'instructions' => $instructions,
					'abilities'    => $ability_list,
				];
			},

			'permission_callback' => static function (): bool|\WP_Error {
				if ( ! is_user_logged_in() ) {
					return new \WP_Error( 'wpcodex_not_authenticated', 'Authentication required.', [ 'status' => 401 ] );
				}
				/** @var string $cap */
				$cap = apply_filters( 'wpcodex_discover_abilities_capability', 'read' );
				if ( ! current_user_can( $cap ) ) {
					return new \WP_Error( 'wpcodex_insufficient_capability', 'Insufficient capability.', [ 'status' => 403 ] );
				}
				return true;
			},

			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	/**
	 * Build the session instructions block including the skill catalog.
	 */
	private static function build_instructions(): string {
		$site_url = home_url();
		$sandbox  = WPCODEX_SANDBOX_DIR;

		$lines = [
			'## WPCodex — AI Operating System for WordPress',
			'',
			'You are connected to a WordPress site via the WPCodex MCP server.',
			'',
			'**Site:** ' . $site_url,
			'**Sandbox directory:** ' . $sandbox,
			'',
			'### Environment',
			'',
			'- Execute arbitrary PHP with `wpcodex/php-execute` — full WordPress environment available (`$wpdb`, all functions, all plugins).',
			'- Run WP-CLI commands with `wpcodex/wpcli-run`.',
			'- Read, write, edit, delete, and list files with the `wpcodex/file-*` abilities.',
			'- Persist PHP code across requests by writing files to the sandbox directory and using `wpcodex/file-disable` / `wpcodex/file-enable` to control loading.',
			'- Manage skill playbooks with `wpcodex/skill-*` abilities.',
			'',
			'### Rules',
			'',
			'- Always inspect before modifying. Use `wpcodex/file-read` or `wpcodex/php-execute` to understand the current state.',
			'- For destructive operations, confirm with the user first.',
			'- Sandbox files run on every WordPress request — keep them lean and non-blocking.',
			'',
		];

		// Append skill catalog if any agentic skills exist.
		$catalog = self::build_skill_catalog();
		if ( '' !== $catalog ) {
			$lines[] = $catalog;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build the ## Available Skills catalog block from the DB.
	 */
	private static function build_skill_catalog(): string {
		$skills = Repository::instance()->all();
		$agentic = array_filter( $skills, static fn( array $s ): bool =>
			(bool) $s['enable_agentic'] && trim( (string) $s['description'] ) !== ''
		);

		if ( empty( $agentic ) ) {
			return '';
		}

		$lines = [
			'## Available Skills',
			'',
			'Call `wpcodex/skill-read` with the skill name to load its full instructions.',
			'',
		];

		foreach ( $agentic as $skill ) {
			$lines[] = sprintf(
				'- **`%s`** — %s',
				esc_html( (string) $skill['name'] ),
				esc_html( (string) $skill['description'] )
			);
		}

		$lines[] = '';
		return implode( "\n", $lines );
	}
}