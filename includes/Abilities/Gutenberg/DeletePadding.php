<?php
/**
 * Ability: wpcodex/gutenberg-delete-pending-batch
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Utils\GutenbergStorage;

/**
 * Class DeletePadding
 *
 * Cancels an entire Gutenberg pending batch and all its non-finalized items.
 *
 * @since 1.0.0
 */
class DeletePadding extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/gutenberg-delete-pending-batch';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Delete Gutenberg Pending Batch', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Cancels an entire Gutenberg pending batch and all its non-finalized items. Does not modify any target post_content.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'batch_id' => [
					'type'        => 'integer',
					'description' => 'Gutenberg batch id to cancel.',
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
		return [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return 'Cancels a Gutenberg batch. It does not alter any target post_content. Use for recovery when a batch is stuck or the agent wants to start over.';
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$batch_id = is_scalar( $input['batch_id'] ?? null ) ? (int) $input['batch_id'] : 0;
		return GutenbergStorage::cancel_batch( $batch_id );
	}
}
