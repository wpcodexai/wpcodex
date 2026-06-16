<?php
/**
 * Ability: wpworker/gutenberg-list-pending-batches
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Gutenberg;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Utils\GutenbergStorage;

/**
 * Class ListPadding
 *
 * Lists compact queue state grouped by Gutenberg batch plus current Block Editor Queue runtime status.
 *
 * @since 1.0.0
 */
class ListPadding extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/gutenberg-list-pending-batches';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'List Gutenberg Pending Batches', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Lists compact queue state grouped by Gutenberg batch for agent recovery, plus the current Block Editor Queue runtime status and curl SSE/poll URLs. Full block specs are not returned.', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'draft', 'ready', 'running', 'finalized', 'failed', 'conflicted', 'canceled', 'stale' ],
					'description' => 'Optional batch status filter.',
				],
				'limit'  => [
					'type'        => 'integer',
					'description' => 'Maximum batches to return. Default 20.',
					'default'     => 20,
				],
			],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'batches'           => [ 'type' => 'array' ],
				'finalizer_runtime' => [ 'type' => 'object' ],
				'user_instruction'  => [ 'type' => 'string' ],
			],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return implode( "\n", [
			'Use this for compact recovery/discovery.',
			'The top-level finalizer_runtime tells you whether the Block Editor Queue page is currently open and includes sse_url/poll_url for curl loops.',
			'If it is offline during Gutenberg work, ask the user to reopen dashboard_url and keep it open.',
			'For one batch, call wpworker/gutenberg-get-pending-batch, then watch the returned sse_url with curl -N or poll_url with curl.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array {
		GutenbergStorage::mark_stale_drafts();

		$status   = is_scalar( $input['status'] ?? null ) ? (string) $input['status'] : '';
		$limit    = max( 1, min( 100, is_scalar( $input['limit'] ?? null ) ? (int) $input['limit'] : 20 ) );
		$statuses = $status !== '' ? [ $status ] : null;

		$batches = [];
		foreach ( GutenbergStorage::get_batches( $statuses, $limit ) as $batch ) {
			$batch     = GutenbergStorage::refresh_batch_runtime_state( $batch );
			$batches[] = GutenbergStorage::shape_batch_summary( $batch );
		}

		return [
			'batches'           => $batches,
			'finalizer_runtime' => GutenbergStorage::finalizer_runtime_status(),
			'user_instruction'  => GutenbergStorage::finalizer_runtime_startup_instruction(),
		];
	}
}
