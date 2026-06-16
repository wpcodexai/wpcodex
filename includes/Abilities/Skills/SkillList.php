<?php
/**
 * Ability: wpworker/skill-list
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Skills;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Skills\Repository;

/**
 * Class SkillList
 *
 * @since 1.0.0
 */
class SkillList extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/skill-list';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'List Skills', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'List all available WPWorker skills with their names, descriptions, and enabled flags. Load this at session start to discover standing instructions for this site.',
			'worker-ai'
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
