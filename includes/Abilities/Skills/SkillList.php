<?php
/**
 * Ability: allyworker/skill-list
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Skills;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Skills\Repository;

/**
 * Class SkillList
 *
 * @since 1.0.0
 */
class SkillList extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/skill-list';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'List Skills', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'List all available AllyWorker skills with their names, descriptions, and enabled flags. Load this at session start to discover standing instructions for this site.',
			'allyworker'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [ 'type' => 'object', 'properties' => [], 'required' => [] ];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [ 'type' => 'string', 'description' => 'JSON array of skill metadata objects.' ];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string {
		$skills = Repository::instance()->all();
		return wp_json_encode( $skills, JSON_PRETTY_PRINT ) ?: '[]';
	}
}
