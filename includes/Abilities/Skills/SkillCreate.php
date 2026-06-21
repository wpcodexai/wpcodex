<?php
/**
 * Ability: allyworker/skill-create
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Skills;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Skills\Repository;
use AllyWorker\Skills\Sources;
use AllyWorker\Skills\Parser;

/**
 * Class SkillCreate
 *
 * @since 1.0.0
 */
class SkillCreate extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/skill-create';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Create Skill', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Create a new AllyWorker skill. The description is the trigger — write it so the agent knows when to fire this skill automatically.',
			'allyworker'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'           => [ 'type' => 'string', 'description' => 'Unique slug for the skill.' ],
				'description'    => [ 'type' => 'string', 'description' => 'One-line trigger description.' ],
				'body'           => [ 'type' => 'string', 'description' => 'Markdown body of the skill.' ],
				'enable_agentic' => [ 'type' => 'boolean', 'description' => 'Auto-fire when description matches. Default: true.', 'default' => true ],
				'enable_prompt'  => [ 'type' => 'boolean', 'description' => 'Expose in AI client prompt menu. Default: true.', 'default' => true ],
				'on_conflict'    => [
					'type'        => 'string',
					'enum'        => [ 'fail', 'replace', 'rename' ],
					'description' => 'Action when a skill with this name already exists. "fail" returns an error (default). "replace" deletes the existing skill and creates new. "rename" creates with a suffixed name (e.g. my-skill-2).',
					'default'     => 'fail',
				],
			],
			'required' => [ 'name', 'description', 'body' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'     => [ 'type' => 'integer', 'description' => 'Database ID of the created skill.' ],
				'name'   => [ 'type' => 'string', 'description' => 'Final skill name (may differ when on_conflict=rename).' ],
				'action' => [ 'type' => 'string', 'enum' => [ 'created', 'replaced', 'renamed' ], 'description' => 'What was done.' ],
			],
			'required' => [ 'id', 'name', 'action' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		foreach ( [ 'name', 'description', 'body' ] as $key ) {
			if ( empty( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) {
				return new \WP_Error(
					'allyworker_invalid_input',
					/* translators: %s argument name */
					sprintf( __( '%s must be a non-empty string.', 'allyworker' ), $key )
				);
			}
		}

		$on_conflict = in_array( $input['on_conflict'] ?? 'fail', [ 'fail', 'replace', 'rename' ], true )
			? (string) ( $input['on_conflict'] ?? 'fail' )
			: 'fail';

		$slug = Parser::normalize_slug( sanitize_title( $input['name'] ) );

		// Guard: prevent overwriting skills owned by external sources.
		$external_label = Sources::exists_in_external_source( $slug );
		if ( null !== $external_label ) {
			return new \WP_Error(
				'allyworker_external_source',
				sprintf(
					/* translators: 1: slug 2: source label */
					__( 'Skill "%1$s" belongs to the "%2$s" source and cannot be modified here.', 'allyworker' ),
					esc_html( $slug ),
					esc_html( $external_label )
				)
			);
		}

		return Repository::instance()->create(
			[
				'name'           => $slug,
				'description'    => sanitize_text_field( $input['description'] ),
				'body'           => wp_kses_post( $input['body'] ),
				'enable_agentic' => isset( $input['enable_agentic'] ) ? (bool) $input['enable_agentic'] : true,
				'enable_prompt'  => isset( $input['enable_prompt'] ) ? (bool) $input['enable_prompt'] : true,
			],
			$on_conflict
		);
	}
}
