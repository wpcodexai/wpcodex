<?php
/**
 * Ability: wpcodex/skill-list
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Skills\Repository;

/**
 * Class SkillList
 *
 * @since 1.0.0
 */
class SkillList extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/skill-list';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'List Skills', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'List all available WPCodex skills with their names, descriptions, and enabled flags. Load this at session start to discover standing instructions for this site.',
			'wpcodex'
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
