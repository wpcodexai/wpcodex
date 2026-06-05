<?php
/**
 * Ability: wpcodex/skill-delete
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillDelete {

	public static function init(): void {
		wp_register_ability( 'wpcodex/skill-delete', [
			'label'       => __( 'Delete Skill', 'wpcodex' ),
			'description' => __( 'Permanently delete a skill by name.', 'wpcodex' ),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [ 'type' => 'string', 'description' => 'The skill name/slug to delete.' ],
				],
				'required' => [ 'name' ],
			],
			'output_schema' => [ 'type' => 'string', 'description' => 'Success message.' ],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'name must be a non-empty string.', 'wpcodex' ) );
				}
				$result = Repository::instance()->delete( $args['name'] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return sprintf( __( 'Skill "%s" deleted.', 'wpcodex' ), esc_html( $args['name'] ) );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
