<?php
/**
 * Ability: wpcodex/skill-list-revisions
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Skills\Repository;

/**
 * Class SkillListRevisions
 *
 * @since 1.0.0
 */
class SkillListRevisions extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/skill-list-revisions';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'List Skill Revisions', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Return up to 10 saved revisions for a skill, newest first. Each revision includes the body, description, and flags captured before the previous save.',
			'wpcodex'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name' => [
					'type'        => 'string',
					'description' => 'Skill name/slug.',
				],
			],
			'required' => [ 'name' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array {
		$name = (string) ( $input['name'] ?? '' );
		if ( '' === $name ) {
			return [];
		}
		return Repository::instance()->get_revisions( $name );
	}
}
