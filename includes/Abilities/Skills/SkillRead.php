<?php
/**
 * Ability: wpcodex/skill-read
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Sources;
use WPCodex\Utils\Helpers;

class SkillRead {
	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/skill-read', [
			'label'       => __( 'Read Skill', 'wpcodex' ),
			'description' => __( 'Read the full body of a skill by name. Searches all registered skill sources (user DB and external plugins).', 'wpcodex' ),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [ 'type' => 'string', 'description' => 'The skill name/slug.' ],
				],
				'required' => [ 'name' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'name'           => [ 'type' => 'string' ],
					'description'    => [ 'type' => 'string' ],
					'body'           => [ 'type' => 'string', 'description' => 'Full skill body (Markdown).' ],
					'enable_agentic' => [ 'type' => 'boolean' ],
					'enable_prompt'  => [ 'type' => 'boolean' ],
					'source'         => [ 'type' => 'string', 'description' => 'Source ID (e.g. "user-db").' ],
					'source_label'   => [ 'type' => 'string', 'description' => 'Human-readable source label.' ],
				],
				'required' => [ 'name', 'body', 'source', 'source_label' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'name must be a non-empty string.', 'wpcodex' ) );
				}

				$skill = Sources::find( $args['name'] );
				if ( null === $skill ) {
					return new \WP_Error(
						'wpcodex_not_found',
						/* translators: %s skill name */
						sprintf( __( 'Skill "%s" not found.', 'wpcodex' ), esc_html( $args['name'] ) )
					);
				}

				return [
					'name'           => (string) ( $skill['name'] ?? $args['name'] ),
					'description'    => (string) ( $skill['description'] ?? '' ),
					'body'           => (string) ( $skill['body'] ?? '' ),
					'enable_agentic' => (bool) ( $skill['enable_agentic'] ?? true ),
					'enable_prompt'  => (bool) ( $skill['enable_prompt'] ?? false ),
					'source'         => (string) ( $skill['source'] ?? 'user-db' ),
					'source_label'   => (string) ( $skill['source_label'] ?? 'User' ),
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
