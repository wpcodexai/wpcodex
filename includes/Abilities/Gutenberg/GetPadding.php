<?php
/**
 * Ability: wpcodex/gutenberg-get-pending-batch
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Utils\GutenbergStorage;

/**
 * Class GetPadding
 *
 * Returns compact status, item summaries, and runtime URLs for one pending batch.
 *
 * @since 1.0.0
 */
class GetPadding extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/gutenberg-get-pending-batch';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Get Gutenberg Pending Batch', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Returns compact status, item summaries, validation errors, Block Editor Queue runtime status, and curl SSE/poll URLs for one pending batch.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'batch_id' => [
					'type'        => 'integer',
					'description' => 'Gutenberg batch id.',
				],
			],
			'required'             => [ 'batch_id' ],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [ 'type' => 'object' ];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return implode( "\n", [
			'Use this to inspect one batch without loading full block_spec payloads.',
			'During finalization, prefer streaming finalizer_runtime.sse_url with curl -N, or polling finalizer_runtime.poll_url with curl, until the batch is finalized, failed, or conflicted.',
			'Item status "prepared" means canonical content is staged but not yet live.',
			'If finalizer_runtime.online becomes false the user closed or lost the Block Editor Queue page; ask them to reopen finalizer_runtime.dashboard_url and keep it open before treating queued changes as live.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		GutenbergStorage::mark_stale_drafts();

		$batch_id = is_scalar( $input['batch_id'] ?? null ) ? (int) $input['batch_id'] : 0;
		$batch    = GutenbergStorage::find_batch( $batch_id );
		if ( ! $batch instanceof \WP_Post ) {
			return new \WP_Error(
				'gutenberg_batch_not_found',
				/* translators: %d: batch ID */
				sprintf( __( 'Gutenberg batch %d was not found.', 'wpcodex' ), $batch_id )
			);
		}

		$batch = GutenbergStorage::refresh_batch_runtime_state( $batch );

		return GutenbergStorage::shape_batch( $batch );
	}
}
