<?php
/**
 * Ability: wpworker/gutenberg-get-finalization-url
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Gutenberg;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Utils\GutenbergStorage;

/**
 * Class GetFinalizationUrl
 *
 * @since 1.0.0
 */
class GetFinalizationUrl extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/gutenberg-get-finalization-url';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Get Gutenberg Finalization URL', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Returns the Block Editor Queue URL and current batch shape for a pending Gutenberg batch. The user opens the queue page and the browser JS finalizer processes the queued block changes automatically.', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'batch_id' => [
					'type'        => 'integer',
					'description' => 'Gutenberg batch id returned by wpworker/gutenberg-write-content or wpworker/gutenberg-enable-batch-finalization.',
				],
			],
			'required' => [ 'batch_id' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'batch_id'          => [ 'type' => 'integer' ],
				'batch_status'      => [ 'type' => 'string' ],
				'finalization_url'  => [ 'type' => 'string', 'description' => 'Open this in a browser to apply the queued Gutenberg changes.' ],
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
			'Call after wpworker/gutenberg-write-content or wpworker/gutenberg-enable-batch-finalization to get the finalization link.',
			'Share finalization_url with the user — they open the Block Editor Queue page which processes the blocks automatically.',
			'Stream finalizer_runtime.sse_url with curl -N or poll poll_url until the batch is finalized, failed, or conflicted.',
			'Do not treat the changes as live until finalization completes.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$batch_id = is_scalar( $input['batch_id'] ?? null ) ? (int) $input['batch_id'] : 0;
		if ( $batch_id <= 0 ) {
			return new \WP_Error( 'wpworker_invalid_input', __( 'batch_id must be a positive integer.', 'worker-ai' ) );
		}

		$batch = GutenbergStorage::find_batch( $batch_id );
		if ( ! $batch instanceof \WP_Post ) {
			return new \WP_Error(
				'wpworker_not_found',
				/* translators: %d batch ID */
				sprintf( __( 'Gutenberg batch %d was not found.', 'worker-ai' ), $batch_id )
			);
		}

		$batch = GutenbergStorage::refresh_batch_runtime_state( $batch );

		return [
			'batch_id'          => $batch->ID,
			'batch_status'      => GutenbergStorage::gb_status( $batch->ID ),
			'finalization_url'  => GutenbergStorage::finalization_url( $batch->ID ),
			'finalizer_runtime' => GutenbergStorage::finalizer_runtime_status( $batch ),
			'user_instruction'  => GutenbergStorage::user_instruction( $batch ),
		];
	}
}
