<?php
/**
 * Ability: allyworker/gutenberg-add-pending-change
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Gutenberg;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Utils\GutenbergStorage;

/**
 * Class AddPaddingChange
 *
 * Adds one Gutenberg block change to a pending batch.
 *
 * @since 1.0.0
 */
class AddPaddingChange extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/gutenberg-add-pending-change';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Add Gutenberg Pending Change', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Adds one Gutenberg block change to a pending batch. If batch_id is omitted a new draft batch is created automatically. The operation is queued — changes are not live until the batch is finalized through the Block Editor Queue.', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'batch_id'         => [
					'type'        => 'integer',
					'description' => 'Gutenberg batch to append to. Omit to create a new batch automatically.',
				],
				'label'            => [
					'type'        => 'string',
					'description' => 'Short human-readable label for this batch (used when auto-creating).',
				],
				'agent_label'      => [
					'type'        => 'string',
					'description' => 'Agent identifier (used when auto-creating batch).',
				],
				'agent_session_id' => [
					'type'        => 'string',
					'description' => 'Agent session ID (used when auto-creating batch).',
				],
				'agent_note'       => [
					'type'        => 'string',
					'description' => 'Longer note describing this change.',
				],
				'target_id'        => [
					'type'        => 'integer',
					'description' => 'Target post/page/template ID (alias: post_id).',
				],
				'post_id'          => [
					'type'        => 'integer',
					'description' => 'Alias for target_id.',
				],
				'target_type'      => [
					'type'        => 'string',
					'description' => 'Target post_type (alias: post_type). Defaults to the target\'s registered post_type.',
				],
				'post_type'        => [
					'type'        => 'string',
					'description' => 'Alias for target_type.',
				],
				'operation'        => [
					'type'        => 'string',
					'enum'        => [ 'replace-content' ],
					'description' => 'Change operation. Currently only replace-content is supported.',
				],
				'block_spec'       => [
					'type'        => 'array',
					'description' => 'Top-level Gutenberg block specs: [{name, attributes?, innerBlocks?}].',
					'items'       => [ 'type' => 'object', 'additionalProperties' => true ],
				],
				'allow_raw_html'   => [
					'type'        => 'boolean',
					'description' => 'Set true to allow block_spec whose leaf blocks are all raw HTML (core/html or core/freeform). Default false; raw-HTML-only specs are rejected unless you explicitly opt in.',
					'default'     => false,
				],
			],
			'required'             => [ 'block_spec' ],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [ 'type' => 'object' ];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return implode( "\n", [
			'Queues one Gutenberg block change. Omit batch_id to auto-create a batch.',
			'After adding all changes, call allyworker/gutenberg-enable-batch-finalization to transition the batch from draft to ready and unlock the Block Editor Queue.',
			'The changes are not live until finalization completes.',
			'If finalizer_runtime.online is false, ask the user to open finalizer_runtime.dashboard_url before enabling finalization.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		// Resolve target.
		$target_id = GutenbergStorage::input_target_id( $input );
		if ( $target_id <= 0 ) {
			return new \WP_Error( 'allyworker_invalid_input', __( 'target_id (or post_id) must be a positive integer.', 'allyworker' ) );
		}
		$target = GutenbergStorage::get_target( $target_id );
		if ( ! $target instanceof \WP_Post ) {
			return new \WP_Error(
				'allyworker_not_found',
				/* translators: %d: post ID */
				sprintf( __( 'Target post %d was not found.', 'allyworker' ), $target_id )
			);
		}
		$target_type = GutenbergStorage::input_target_type( $input, $target );

		// Normalise and validate block_spec.
		$blocks = GutenbergStorage::normalize_blocks( $input['block_spec'] ?? null );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}

		$allow_raw_html = isset( $input['allow_raw_html'] ) && true === $input['allow_raw_html'];
		if ( ! $allow_raw_html && GutenbergStorage::blocks_are_raw_html_only( $blocks ) ) {
			return new \WP_Error(
				'gutenberg_raw_html_only',
				'block_spec contains only raw HTML blocks (core/html / core/freeform). Use structured Gutenberg blocks instead. If raw HTML is intentional set allow_raw_html:true.',
				[ 'status' => 400 ]
			);
		}

		$operation = is_string( $input['operation'] ?? null ) ? $input['operation'] : 'replace-content';

		// Check for conflicting active item on the same target.
		$existing = GutenbergStorage::active_item_for_target( $target_id, $target_type );
		if ( $existing instanceof \WP_Post ) {
			$conflict = GutenbergStorage::conflict_payload( $existing );
			return new \WP_Error(
				'gutenberg_target_already_queued',
				sprintf(
					'Target %s #%d already has a non-terminal queued Gutenberg change in batch #%d. Cancel or finalize that batch first.',
					$target_type,
					$target_id,
					$existing->post_parent
				),
				array_merge( [ 'status' => 409 ], $conflict )
			);
		}

		// Resolve or create batch.
		$batch_id_input = is_scalar( $input['batch_id'] ?? null ) ? (int) $input['batch_id'] : 0;
		if ( $batch_id_input > 0 ) {
			$batch = GutenbergStorage::find_batch( $batch_id_input );
			if ( ! $batch instanceof \WP_Post ) {
				return new \WP_Error(
					'gutenberg_batch_not_found',
					/* translators: %d: batch ID */
					sprintf( __( 'Gutenberg batch %d was not found.', 'allyworker' ), $batch_id_input )
				);
			}
			if ( GutenbergStorage::gb_status( $batch->ID ) !== GutenbergStorage::STATUS_DRAFT ) {
				return new \WP_Error(
					'gutenberg_batch_not_draft',
					sprintf(
						'Gutenberg batch %d is %s; only draft batches accept new items.',
						$batch->ID,
						GutenbergStorage::gb_status( $batch->ID )
					),
					[ 'status' => 409 ]
				);
			}
		} else {
			GutenbergStorage::mark_stale_drafts();
			$label            = is_string( $input['label'] ?? null ) ? $input['label'] : '';
			$agent_label      = is_string( $input['agent_label'] ?? null ) ? $input['agent_label'] : '';
			$agent_session_id = is_string( $input['agent_session_id'] ?? null ) ? $input['agent_session_id'] : '';
			$agent_note       = is_string( $input['agent_note'] ?? null ) ? $input['agent_note'] : '';
			$created_id       = GutenbergStorage::create_batch( $label, $agent_label, $agent_session_id, $agent_note );
			if ( is_wp_error( $created_id ) ) {
				return $created_id;
			}
			$batch = GutenbergStorage::find_batch( $created_id );
			if ( ! $batch instanceof \WP_Post ) {
				return new \WP_Error( 'gutenberg_batch_not_found', 'The newly created Gutenberg batch could not be retrieved.' );
			}
		}

		$item_id = GutenbergStorage::create_item( $batch->ID, $target_id, $target_type, $operation, $blocks );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		$runtime = GutenbergStorage::finalizer_runtime_status();

		return [
			'batch_id'              => $batch->ID,
			'item_id'               => $item_id,
			'batch_status'          => GutenbergStorage::gb_status( $batch->ID ),
			'target'                => [
				'id'    => $target->ID,
				'type'  => $target_type,
				'title' => GutenbergStorage::target_title( $target ),
			],
			'finalization_required' => true,
			'finalizer_runtime'     => $runtime,
			'user_instruction'      => GutenbergStorage::finalizer_runtime_startup_instruction(),
		];
	}
}
