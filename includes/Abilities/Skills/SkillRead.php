<?php
/**
 * Ability: allyworker/skill-read
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Skills;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Skills\Sources;

/**
 * Class SkillRead
 *
 * @since 1.0.0
 */
class SkillRead extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/skill-read';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Read Skill', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Read the full body of a skill by name. Searches all registered skill sources (user DB and external plugins).', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name' => [ 'type' => 'string', 'description' => 'The skill name/slug.' ],
			],
			'required' => [ 'name' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'           => [ 'type' => 'string' ],
				'description'    => [ 'type' => 'string' ],
				'body'           => [ 'type' => 'string', 'description' => 'Full skill body (Markdown).' ],
				'enable_agentic' => [ 'type' => 'boolean' ],
				'enable_prompt'  => [ 'type' => 'boolean' ],
				'source'         => [ 'type' => 'string', 'description' => 'Source ID (e.g. "user-db").' ],
				'source_label'   => [ 'type' => 'string', 'description' => 'Human-readable source label.' ],
			],
			'required' => [ 'name', 'body', 'source', 'source_label' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['name'] ) || ! is_string( $input['name'] ) ) {
			return new \WP_Error( 'allyworker_invalid_input', __( 'name must be a non-empty string.', 'allyworker' ) );
		}

		$skill = Sources::find( $input['name'] );
		if ( null === $skill ) {
			return new \WP_Error(
				'allyworker_not_found',
				/* translators: %s skill name */
				sprintf( __( 'Skill "%s" not found.', 'allyworker' ), esc_html( $input['name'] ) )
			);
		}

		return [
			'name'           => (string) ( $skill['name'] ?? $input['name'] ),
			'description'    => (string) ( $skill['description'] ?? '' ),
			'body'           => (string) ( $skill['body'] ?? '' ),
			'enable_agentic' => (bool) ( $skill['enable_agentic'] ?? true ),
			'enable_prompt'  => (bool) ( $skill['enable_prompt'] ?? false ),
			'source'         => (string) ( $skill['source'] ?? 'user-db' ),
			'source_label'   => (string) ( $skill['source_label'] ?? 'User' ),
		];
	}
}
