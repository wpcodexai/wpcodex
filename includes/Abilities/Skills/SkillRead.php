<?php
/**
 * Ability: wpcodex/skill-read
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillRead {
	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}
	public static function init(): void {
		wp_register_ability( 'wpcodex/skill-read', [
			'label'       => __( 'Read Skill', 'wpcodex' ),
			'description' => __( 'Read the full body of a skill by name.', 'wpcodex' ),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [ 'type' => 'string', 'description' => 'The skill name/slug.' ],
				],
				'required' => [ 'name' ],
			],
			'output_schema' => [ 'type' => 'string', 'description' => 'Skill body as Markdown.' ],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'name must be a non-empty string.', 'wpcodex' ) );
				}
				$skill = Repository::instance()->find( $args['name'] );
				if ( null === $skill ) {
					return new \WP_Error(
						'wpcodex_not_found',
						/* translators: %s skill name */
						sprintf( __( 'Skill "%s" not found.', 'wpcodex' ), esc_html( $args['name'] ) )
					);
				}
				return $skill['body'] ?? '';
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
