<?php
/**
 * Ability: wpworker/skill-restore-revision
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Skills;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Skills\Repository;

/**
 * Class SkillRestoreRevision
 *
 * @since 1.0.0
 */
class SkillRestoreRevision extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/skill-restore-revision';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Restore Skill Revision', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Restore a skill to a previously saved revision. The current state is automatically snapshotted first, so the restore is itself reversible.',
			'worker-ai'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'revision_id' => [
					'type'        => 'integer',
					'description' => 'Numeric ID of the revision to restore (from wpworker/skill-list-revisions).',
				],
			],
			'required' => [ 'revision_id' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'revision_id' => [ 'type' => 'integer' ],
				'restored'    => [ 'type' => 'boolean' ],
			],
			'required' => [ 'revision_id', 'restored' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$revision_id = (int) ( $input['revision_id'] ?? 0 );

		if ( $revision_id <= 0 ) {
			return new \WP_Error( 'wpworker_invalid_input', __( 'revision_id must be a positive integer.', 'worker-ai' ) );
		}

		$result = Repository::instance()->restore_revision( $revision_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'revision_id' => $revision_id,
			'restored'    => true,
		];
	}
}
