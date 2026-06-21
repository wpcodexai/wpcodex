<?php
/**
 * Ability: allyworker/skill-update
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Skills;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Skills\Repository;

/**
 * Class SkillUpdate
 *
 * @since 1.0.0
 */
class SkillUpdate extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/skill-update';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Update Skill', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Update an existing skill by name. Pass only the fields you want to change.', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'           => [ 'type' => 'string', 'description' => 'The skill name/slug to update.' ],
				'description'    => [ 'type' => 'string' ],
				'body'           => [ 'type' => 'string' ],
				'enable_agentic' => [ 'type' => 'boolean' ],
				'enable_prompt'  => [ 'type' => 'boolean' ],
			],
			'required' => [ 'name' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['name'] ) || ! is_string( $input['name'] ) ) {
			return new \WP_Error( 'allyworker_invalid_input', __( 'name must be a non-empty string.', 'allyworker' ) );
		}
		$data = [];
		if ( isset( $input['description'] ) ) {
			$data['description'] = sanitize_text_field( $input['description'] );
		}
		if ( isset( $input['body'] ) ) {
			$data['body'] = wp_kses_post( $input['body'] );
		}
		if ( isset( $input['enable_agentic'] ) ) {
			$data['enable_agentic'] = (bool) $input['enable_agentic'];
		}
		if ( isset( $input['enable_prompt'] ) ) {
			$data['enable_prompt'] = (bool) $input['enable_prompt'];
		}
		if ( empty( $data ) ) {
			return new \WP_Error( 'allyworker_invalid_input', __( 'No fields to update were provided.', 'allyworker' ) );
		}
		return Repository::instance()->update( $input['name'], $data );
	}
}
