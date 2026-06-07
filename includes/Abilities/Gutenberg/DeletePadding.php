<?php
/**
 * Ability: wpcodex/gutenberg-delete-pending-batch
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class DeletePadding
 *
 * Cancels an entire Gutenberg pending batch and all its non-finalized items.
 *
 * @since 1.0.0
 */
class DeletePadding {

	/**
	 * Register the wpcodex/register_abilities hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	/**
	 * Register the ability with the WordPress Abilities API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		wp_register_ability( 'wpcodex/gutenberg-delete-pending-batch', [
			'label'       => __( 'Delete Gutenberg Pending Batch', 'wpcodex' ),
			'description' => __( 'Cancels an entire Gutenberg pending batch and all its non-finalized items. Does not modify any target post_content.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'batch_id' => [
						'type'        => 'integer',
						'description' => 'Gutenberg batch id to cancel.',
					],
				],
				'required'             => [ 'batch_id' ],
				'additionalProperties' => false,
			],

			'output_schema' => [ 'type' => 'object' ],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$batch_id = is_scalar( $args['batch_id'] ?? null ) ? (int) $args['batch_id'] : 0;
				return GutenbergStorage::cancel_batch( $batch_id );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Cancels a Gutenberg batch. It does not alter any target post_content. Use for recovery when a batch is stuck or the agent wants to start over.',
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
