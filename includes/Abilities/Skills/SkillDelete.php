<?php
/**
 * Ability: allyworker/skill-delete
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Skills;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Skills\Repository;

/**
 * Class SkillDelete
 *
 * @since 1.0.0
 */
class SkillDelete extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/skill-delete';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Delete Skill', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Permanently delete a skill by name. Idempotent — returns success when the skill does not exist.', 'allyworker' );
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
			return new \WP_Error( 'allyworker_invalid_input', __( 'name must be a non-empty string.', 'allyworker' ) );
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
