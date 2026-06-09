<?php
/**
 * Ability: wpcodex/skill-restore-revision
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillRestoreRevision {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/skill-restore-revision', [
			'label'       => __( 'Restore Skill Revision', 'wpcodex' ),
			'description' => __(
				'Restore a skill to a previously saved revision. The current state is automatically snapshotted first, so the restore is itself reversible.',
				'wpcodex'
			),
			'category'    => 'wpcodex-skills',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'revision_id' => [
						'type'        => 'integer',
						'description' => 'Numeric ID of the revision to restore (from wpcodex/skill-list-revisions).',
					],
				],
				'required' => [ 'revision_id' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'revision_id' => [ 'type' => 'integer' ],
					'restored'    => [ 'type' => 'boolean' ],
				],
				'required' => [ 'revision_id', 'restored' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$revision_id = (int) ( $args['revision_id'] ?? 0 );

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
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
