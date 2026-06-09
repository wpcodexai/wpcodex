<?php
/**
 * Ability: wpcodex/skill-list-revisions
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillListRevisions {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/skill-list-revisions', [
			'label'       => __( 'List Skill Revisions', 'wpcodex' ),
			'description' => __(
				'Return up to 10 saved revisions for a skill, newest first. Each revision includes the body, description, and flags captured before the previous save.',
				'wpcodex'
			),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [
						'type'        => 'string',
						'description' => 'Skill name/slug.',
					],
				],
				'required' => [ 'name' ],
			],

			'output_schema' => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'properties' => [
						'id'             => [ 'type' => 'integer' ],
						'skill_name'     => [ 'type' => 'string' ],
						'description'    => [ 'type' => 'string' ],
						'body'           => [ 'type' => 'string' ],
						'enable_agentic' => [ 'type' => 'boolean' ],
						'enable_prompt'  => [ 'type' => 'boolean' ],
						'created_at'     => [ 'type' => 'string' ],
					],
					'required' => [ 'id', 'skill_name', 'description', 'body', 'enable_agentic', 'enable_prompt', 'created_at' ],
				],
			],

			'execute_callback' => static function ( array $args ): array {
				$name = (string) ( $args['name'] ?? '' );
				if ( '' === $name ) {
					return [];
				}
				return Repository::instance()->get_revisions( $name );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
