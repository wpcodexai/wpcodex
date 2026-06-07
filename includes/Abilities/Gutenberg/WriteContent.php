<?php
/**
 * Ability: wpcodex/gutenberg-write-content
 *
 * Convenience wrapper: creates a batch, adds one item, and enables finalization
 * in a single call. For multi-post batches use the individual abilities:
 * wpcodex/gutenberg-create-pending-batch → wpcodex/gutenberg-add-pending-change (×n)
 * → wpcodex/gutenberg-enable-batch-finalization.
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class WriteContent
 *
 * Convenience ability: creates a batch, adds one block change, enables finalization in one step.
 *
 * @since 1.0.0
 */
class WriteContent {

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
		wp_register_ability( 'wpcodex/gutenberg-write-content', [
			'label'       => __( 'Write Gutenberg Content', 'wpcodex' ),
			'description' => __( 'Convenience: creates a batch, adds one block change, and enables finalization in one step. For multiple targets in a single batch use the individual wpcodex/gutenberg-create-pending-batch → wpcodex/gutenberg-add-pending-change → wpcodex/gutenberg-enable-batch-finalization flow.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'post_id'        => [
						'type'        => 'integer',
						'description' => 'Target post/page/template ID.',
					],
					'block_spec'     => [
						'type'        => 'array',
						'description' => 'Top-level Gutenberg block specs: [{name, attributes?, innerBlocks?}].',
						'items'       => [ 'type' => 'object', 'additionalProperties' => true ],
					],
					'label'          => [
						'type'        => 'string',
						'description' => 'Short human-readable label for the batch.',
						'default'     => '',
					],
					'agent_note'     => [
						'type'        => 'string',
						'description' => 'Longer note for the user about what this change does.',
						'default'     => '',
					],
					'allow_raw_html' => [
						'type'        => 'boolean',
						'description' => 'Set true to allow block_spec whose leaf blocks are all raw HTML.',
						'default'     => false,
					],
				],
				'required' => [ 'post_id', 'block_spec' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'batch_id'          => [ 'type' => 'integer' ],
					'item_id'           => [ 'type' => 'integer' ],
					'post_id'           => [ 'type' => 'integer' ],
					'post_title'        => [ 'type' => 'string' ],
					'batch_status'      => [ 'type' => 'string' ],
					'finalization_url'  => [ 'type' => 'string' ],
					'finalizer_runtime' => [ 'type' => 'object' ],
					'user_instruction'  => [ 'type' => 'string' ],
				],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
				if ( $post_id <= 0 ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'post_id must be a positive integer.', 'wpcodex' ) );
				}

				$target = GutenbergStorage::get_target( $post_id );
				if ( ! $target instanceof \WP_Post ) {
					return new \WP_Error(
						'wpcodex_not_found',
						/* translators: %d post ID */
						sprintf( __( 'Post %d was not found.', 'wpcodex' ), $post_id )
					);
				}

				// Normalise block_spec.
				$blocks = GutenbergStorage::normalize_blocks( $args['block_spec'] ?? null );
				if ( is_wp_error( $blocks ) ) {
					return $blocks;
				}

				$allow_raw_html = isset( $args['allow_raw_html'] ) && true === $args['allow_raw_html'];
				if ( ! $allow_raw_html && GutenbergStorage::blocks_are_raw_html_only( $blocks ) ) {
					return new \WP_Error(
						'gutenberg_raw_html_only',
						'block_spec contains only raw HTML blocks. Use structured Gutenberg blocks instead, or set allow_raw_html:true.',
						[ 'status' => 400 ]
					);
				}

				$label      = is_string( $args['label'] ?? null ) ? trim( $args['label'] ) : '';
				$agent_note = is_string( $args['agent_note'] ?? null ) ? trim( $args['agent_note'] ) : '';

				if ( $label === '' ) {
					$label = sprintf(
						/* translators: %1$s post type, %2$d post ID */
						__( 'Gutenberg change for %1$s #%2$d', 'wpcodex' ),
						$target->post_type,
						$target->ID
					);
				}

				// 1. Create batch.
				GutenbergStorage::mark_stale_drafts();
				$batch_id = GutenbergStorage::create_batch( $label, '', '', $agent_note );
				if ( is_wp_error( $batch_id ) ) {
					return $batch_id;
				}

				// 2. Add item.
				$item_id = GutenbergStorage::create_item( $batch_id, $target->ID, $target->post_type, 'replace-content', $blocks );
				if ( is_wp_error( $item_id ) ) {
					return $item_id;
				}

				// 3. Enable finalization (draft → ready).
				$batch = GutenbergStorage::find_batch( $batch_id );
				if ( ! $batch instanceof \WP_Post ) {
					return new \WP_Error( 'gutenberg_batch_not_found', 'The newly created Gutenberg batch could not be retrieved.' );
				}
				if ( ! GutenbergStorage::atomic_status_transition( $batch->ID, [ GutenbergStorage::STATUS_DRAFT ], GutenbergStorage::STATUS_READY ) ) {
					return new \WP_Error( 'gutenberg_batch_transition_failed', 'Could not transition the batch to ready.', [ 'status' => 500 ] );
				}
				update_post_meta( $batch->ID, GutenbergStorage::META_READY_AT, GutenbergStorage::now_mysql() );
				GutenbergStorage::set_status( $item_id, GutenbergStorage::STATUS_READY );

				$fresh   = GutenbergStorage::find_batch( $batch_id ) ?? $batch;
				$runtime = GutenbergStorage::finalizer_runtime_status( $fresh );

				return [
					'batch_id'          => $fresh->ID,
					'item_id'           => $item_id,
					'post_id'           => $target->ID,
					'post_title'        => $target->post_title,
					'batch_status'      => GutenbergStorage::gb_status( $fresh->ID ),
					'finalization_url'  => GutenbergStorage::finalization_url( $fresh->ID ),
					'finalizer_runtime' => $runtime,
					'user_instruction'  => GutenbergStorage::user_instruction( $fresh ),
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => implode( "\n", [
						'Queues one Gutenberg block change and enables finalization in a single call.',
						'After calling this, the Block Editor Queue (finalization_url / finalizer_runtime.dashboard_url) will begin processing automatically if it is open.',
						'If finalizer_runtime.online is false, ask the user to open finalization_url and keep it open.',
						'Stream sse_url with curl -N or poll poll_url with curl until the batch is finalized, failed, or conflicted.',
						'Do not treat the Gutenberg changes as live until finalization completes.',
					] ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
