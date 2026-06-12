<?php
/**
 * Ability: wpcodex/skill-restore-revision
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Skills\Repository;

/**
 * Class SkillRestoreRevision
 *
 * @since 1.0.0
 */
class SkillRestoreRevision extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-skills';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/skill-restore-revision';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Restore Skill Revision', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Restore a skill to a previously saved revision. The current state is automatically snapshotted first, so the restore is itself reversible.',
			'wpcodex'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'revision_id' => [
					'type'        => 'integer',
					'description' => 'Numeric ID of the revision to restore (from wpcodex/skill-list-revisions).',
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
			return new \WP_Error( 'wpcodex_invalid_input', __( 'revision_id must be a positive integer.', 'wpcodex' ) );
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
