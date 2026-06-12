<?php
/**
 * Ability: wpcodex/gutenberg-delete-pending-change
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Utils\GutenbergStorage;

/**
 * Class DeletePaddingChange
 *
 * Cancels one Gutenberg pending item without touching target post_content.
 *
 * @since 1.0.0
 */
class DeletePaddingChange extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/gutenberg-delete-pending-change';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Delete Gutenberg Pending Change', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Cancels one Gutenberg pending item without touching target post_content. Use for per-item recovery when you need to remove one change from a batch.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'item_id' => [
					'type'        => 'integer',
					'description' => 'Gutenberg pending item id to cancel.',
				],
			],
			'required'             => [ 'item_id' ],
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
		return 'Cancels one queued item only. It does not alter target post_content. If all remaining items in the batch are also canceled, the batch itself becomes canceled.';
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$item_id = is_scalar( $input['item_id'] ?? null ) ? (int) $input['item_id'] : 0;
		return GutenbergStorage::cancel_item( $item_id );
	}
}
