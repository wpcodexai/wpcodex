<?php
/**
 * Ability: wpcodex/gutenberg-enable-batch-finalization
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class EnableFinalization
 *
 * Transitions a draft Gutenberg batch to ready so the Block Editor Queue can begin finalization.
 *
 * @since 1.0.0
 */
class EnableFinalization {

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
		wp_register_ability( 'wpcodex/gutenberg-enable-batch-finalization', [
			'label'       => __( 'Enable Gutenberg Batch Finalization', 'wpcodex' ),
			'description' => __( 'Transitions a draft Gutenberg batch to ready so the Block Editor Queue can begin finalization. The browser-side JS runtime picks it up automatically when the queue page is open.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'batch_id' => [
						'type'        => 'integer',
						'description' => 'Gutenberg batch id to enable finalization for.',
					],
				],
				'required'             => [ 'batch_id' ],
				'additionalProperties' => false,
			],

			'output_schema' => [ 'type' => 'object' ],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$batch_id = is_scalar( $args['batch_id'] ?? null ) ? (int) $args['batch_id'] : 0;
				$batch    = GutenbergStorage::find_batch( $batch_id );
				if ( ! $batch instanceof \WP_Post ) {
					return new \WP_Error(
						'gutenberg_batch_not_found',
						/* translators: %d: batch ID */
						sprintf( __( 'Gutenberg batch %d was not found.', 'wpcodex' ), $batch_id )
					);
				}

				$current_status = GutenbergStorage::gb_status( $batch->ID );
				if ( $current_status !== GutenbergStorage::STATUS_DRAFT ) {
					// Idempotent: if already ready (or further along), return current state.
					if ( $current_status === GutenbergStorage::STATUS_READY ) {
						$fresh   = GutenbergStorage::find_batch( $batch->ID ) ?? $batch;
						$runtime = GutenbergStorage::finalizer_runtime_status( $fresh );
						return [
							'batch_id'              => $fresh->ID,
							'batch_status'          => GutenbergStorage::gb_status( $fresh->ID ),
							'finalization_required' => true,
							'finalization_url'      => GutenbergStorage::finalization_url( $fresh->ID ),
							'finalizer_runtime'     => $runtime,
							'user_instruction'      => GutenbergStorage::user_instruction( $fresh ),
						];
					}
					return new \WP_Error(
						'gutenberg_batch_not_draft',
						sprintf(
							'Gutenberg batch %d is already %s; only draft batches can be enabled.',
							$batch->ID,
							$current_status
						),
						[ 'status' => 409 ]
					);
				}

				// Ensure there is at least one item.
				$items = GutenbergStorage::get_items( $batch->ID );
				if ( $items === [] ) {
					return new \WP_Error(
						'gutenberg_batch_empty',
						sprintf( 'Gutenberg batch %d has no items. Add at least one change with wpcodex/gutenberg-add-pending-change.', $batch->ID ),
						[ 'status' => 409 ]
					);
				}

				// Transition batch: draft → ready.
				if ( ! GutenbergStorage::atomic_status_transition( $batch->ID, [ GutenbergStorage::STATUS_DRAFT ], GutenbergStorage::STATUS_READY ) ) {
					return new \WP_Error(
						'gutenberg_batch_transition_failed',
						'The batch status could not be updated to ready. It may have changed concurrently.',
						[ 'status' => 409 ]
					);
				}
				update_post_meta( $batch->ID, GutenbergStorage::META_READY_AT, GutenbergStorage::now_mysql() );

				// Mark all draft items as ready.
				foreach ( GutenbergStorage::get_items( $batch->ID, [ GutenbergStorage::STATUS_DRAFT ] ) as $item ) {
					GutenbergStorage::set_status( $item->ID, GutenbergStorage::STATUS_READY );
				}

				$fresh   = GutenbergStorage::find_batch( $batch->ID ) ?? $batch;
				$runtime = GutenbergStorage::finalizer_runtime_status( $fresh );

				return [
					'batch_id'              => $fresh->ID,
					'batch_status'          => GutenbergStorage::gb_status( $fresh->ID ),
					'finalization_required' => true,
					'finalization_url'      => GutenbergStorage::finalization_url( $fresh->ID ),
					'finalizer_runtime'     => $runtime,
					'user_instruction'      => GutenbergStorage::user_instruction( $fresh ),
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => implode( "\n", [
						'Call after wpcodex/gutenberg-add-pending-change to transition the batch from draft to ready.',
						'After calling this, the Block Editor Queue page will automatically begin finalization if it is open.',
						'Check finalizer_runtime.online; if false ask the user to open finalization_url (the queue dashboard) and keep it open.',
						'Watch sse_url with curl -N or poll poll_url until the batch is finalized, failed, or conflicted.',
						'Do not treat the Gutenberg changes as live until finalization completes.',
					] ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
