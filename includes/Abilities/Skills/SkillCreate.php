<?php
/**
 * Ability: wpcodex/skill-create
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillCreate {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/skill-create', [
			'label'       => __( 'Create Skill', 'wpcodex' ),
			'description' => __(
				'Create a new WPCodex skill. The description is the trigger — write it so the agent knows when to fire this skill automatically.',
				'wpcodex'
			),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name'           => [ 'type' => 'string', 'description' => 'Unique slug for the skill.' ],
					'description'    => [ 'type' => 'string', 'description' => 'One-line trigger description.' ],
					'body'           => [ 'type' => 'string', 'description' => 'Markdown body of the skill.' ],
					'enable_agentic' => [ 'type' => 'boolean', 'description' => 'Auto-fire when description matches. Default: true.', 'default' => true ],
					'enable_prompt'  => [ 'type' => 'boolean', 'description' => 'Expose in AI client prompt menu. Default: true.', 'default' => true ],
				],
				'required' => [ 'name', 'description', 'body' ],
			],
			'output_schema' => [ 'type' => 'string', 'description' => 'Success message.' ],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				foreach ( [ 'name', 'description', 'body' ] as $key ) {
					if ( empty( $args[ $key ] ) || ! is_string( $args[ $key ] ) ) {
						return new \WP_Error(
							'wpcodex_invalid_input',
							/* translators: %s argument name */
							sprintf( __( '%s must be a non-empty string.', 'wpcodex' ), $key )
						);
					}
				}
				$result = Repository::instance()->create( [
					'name'           => sanitize_title( $args['name'] ),
					'description'    => sanitize_text_field( $args['description'] ),
					'body'           => wp_kses_post( $args['body'] ),
					'enable_agentic' => isset( $args['enable_agentic'] ) ? (bool) $args['enable_agentic'] : true,
					'enable_prompt'  => isset( $args['enable_prompt'] ) ? (bool) $args['enable_prompt'] : true,
				] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return sprintf( __( 'Skill "%s" created (ID: %d).', 'wpcodex' ), esc_html( $args['name'] ), $result );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
