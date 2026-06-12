<?php
/**
 * Ability: wpcodex/skill-delete
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Skills\Repository;

/**
 * Class SkillDelete
 *
 * @since 1.0.0
 */
class SkillDelete extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/skill-delete';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Delete Skill', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Permanently delete a skill by name. Idempotent — returns success when the skill does not exist.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name' => [ 'type' => 'string', 'description' => 'The skill name/slug to delete.' ],
			],
			'required' => [ 'name' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'    => [ 'type' => 'string', 'description' => 'Skill name.' ],
				'deleted' => [ 'type' => 'boolean', 'description' => 'True when the skill was deleted; false when it did not exist.' ],
			],
			'required' => [ 'name', 'deleted' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['name'] ) || ! is_string( $input['name'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'name must be a non-empty string.', 'wpcodex' ) );
		}

		$name    = $input['name'];
		$existed = null !== Repository::instance()->find( $name );
		$result  = Repository::instance()->delete( $name );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'name'    => $name,
			'deleted' => $existed,
		];
	}
}
