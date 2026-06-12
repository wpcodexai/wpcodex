<?php
/**
 * Ability: wpcodex/skill-update
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Skills\Repository;

/**
 * Class SkillUpdate
 *
 * @since 1.0.0
 */
class SkillUpdate extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/skill-update';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Update Skill', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Update an existing skill by name. Pass only the fields you want to change.', 'wpcodex' );
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
			return new \WP_Error( 'wpcodex_invalid_input', __( 'name must be a non-empty string.', 'wpcodex' ) );
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
			return new \WP_Error( 'wpcodex_invalid_input', __( 'No fields to update were provided.', 'wpcodex' ) );
		}
		return Repository::instance()->update( $input['name'], $data );
	}
}
