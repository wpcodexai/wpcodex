<?php
/**
 * Ability: wpcodex/skill-update
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillUpdate {
	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/skill-update', [
			'label'       => __( 'Update Skill', 'wpcodex' ),
			'description' => __( 'Update an existing skill by name. Pass only the fields you want to change.', 'wpcodex' ),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name'           => [ 'type' => 'string', 'description' => 'The skill name/slug to update.' ],
					'description'    => [ 'type' => 'string' ],
					'body'           => [ 'type' => 'string' ],
					'enable_agentic' => [ 'type' => 'boolean' ],
					'enable_prompt'  => [ 'type' => 'boolean' ],
				],
				'required' => [ 'name' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'name'           => [ 'type' => 'string', 'description' => 'Skill name.' ],
					'changed_fields' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'List of fields that were updated.',
					],
				],
				'required' => [ 'name', 'changed_fields' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'name must be a non-empty string.', 'wpcodex' ) );
				}
				$data = [];
				if ( isset( $args['description'] ) ) {
					$data['description'] = sanitize_text_field( $args['description'] );
				}
				if ( isset( $args['body'] ) ) {
					$data['body'] = wp_kses_post( $args['body'] );
				}
				if ( isset( $args['enable_agentic'] ) ) {
					$data['enable_agentic'] = (bool) $args['enable_agentic'];
				}
				if ( isset( $args['enable_prompt'] ) ) {
					$data['enable_prompt'] = (bool) $args['enable_prompt'];
				}
				if ( empty( $data ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'No fields to update were provided.', 'wpcodex' ) );
				}
				return Repository::instance()->update( $args['name'], $data );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
