<?php
/**
 * Ability: allyworker/gutenberg-write-content
 *
 * Convenience wrapper: creates a batch, adds one item, and enables finalization
 * in a single call.
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Gutenberg;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Utils\GutenbergStorage;

/**
 * Class WriteContent
 *
 * @since 1.0.0
 */
class WriteContent extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/gutenberg-write-content';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Write Gutenberg Content', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Convenience: creates a batch, adds one block change, and enables finalization in one step. For multiple targets in a single batch use the individual allyworker/gutenberg-create-pending-batch → allyworker/gutenberg-add-pending-change → allyworker/gutenberg-enable-batch-finalization flow.', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return implode( "\n", [
			'Queues one Gutenberg block change and enables finalization in a single call.',
			'After calling this, the Block Editor Queue (finalization_url / finalizer_runtime.dashboard_url) will begin processing automatically if it is open.',
			'If finalizer_runtime.online is false, ask the user to open finalization_url and keep it open.',
			'Stream sse_url with curl -N or poll poll_url with curl until the batch is finalized, failed, or conflicted.',
			'Do not treat the Gutenberg changes as live until finalization completes.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'allyworker_invalid_input', __( 'post_id must be a positive integer.', 'allyworker' ) );
		}

		$target = GutenbergStorage::get_target( $post_id );
		if ( ! $target instanceof \WP_Post ) {
			return new \WP_Error(
				'allyworker_not_found',
				/* translators: %d post ID */
				sprintf( __( 'Post %d was not found.', 'allyworker' ), $post_id )
			);
		}

		$blocks = GutenbergStorage::normalize_blocks( $input['block_spec'] ?? null );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}

		$allow_raw_html = isset( $input['allow_raw_html'] ) && true === $input['allow_raw_html'];
		if ( ! $allow_raw_html && GutenbergStorage::blocks_are_raw_html_only( $blocks ) ) {
			return new \WP_Error(
				'gutenberg_raw_html_only',
				'block_spec contains only raw HTML blocks. Use structured Gutenberg blocks instead, or set allow_raw_html:true.',
				[ 'status' => 400 ]
			);
		}

		$label      = is_string( $input['label'] ?? null ) ? trim( $input['label'] ) : '';
		$agent_note = is_string( $input['agent_note'] ?? null ) ? trim( $input['agent_note'] ) : '';

		if ( $label === '' ) {
			$label = sprintf(
				/* translators: %1$s post type, %2$d post ID */
				__( 'Gutenberg change for %1$s #%2$d', 'allyworker' ),
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
	}
}
