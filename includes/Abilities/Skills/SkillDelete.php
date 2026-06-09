<?php
/**
 * Ability: wpcodex/skill-delete
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillDelete {
	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/skill-delete', [
			'label'       => __( 'Delete Skill', 'wpcodex' ),
			'description' => __( 'Permanently delete a skill by name. Idempotent — returns success when the skill does not exist.', 'wpcodex' ),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [ 'type' => 'string', 'description' => 'The skill name/slug to delete.' ],
				],
				'required' => [ 'name' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'name'    => [ 'type' => 'string', 'description' => 'Skill name.' ],
					'deleted' => [ 'type' => 'boolean', 'description' => 'True when the skill was deleted; false when it did not exist.' ],
				],
				'required' => [ 'name', 'deleted' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'name must be a non-empty string.', 'wpcodex' ) );
				}

				$name     = $args['name'];
				$existed  = null !== Repository::instance()->find( $name );
				$result   = Repository::instance()->delete( $name );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return [
					'name'    => $name,
					'deleted' => $existed,
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
