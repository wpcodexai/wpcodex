<?php
/**
 * Ability: wpworker/skill-read
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Skills;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Skills\Sources;

/**
 * Class SkillRead
 *
 * @since 1.0.0
 */
class SkillRead extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/skill-read';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Read Skill', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Read the full body of a skill by name. Searches all registered skill sources (user DB and external plugins).', 'worker-ai' );
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
			return new \WP_Error( 'wpworker_invalid_input', __( 'name must be a non-empty string.', 'worker-ai' ) );
		}

		$skill = Sources::find( $input['name'] );
		if ( null === $skill ) {
			return new \WP_Error(
				'wpworker_not_found',
				/* translators: %s skill name */
				sprintf( __( 'Skill "%s" not found.', 'worker-ai' ), esc_html( $input['name'] ) )
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
