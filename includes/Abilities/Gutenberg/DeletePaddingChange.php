<?php
/**
 * Ability: wpcodex/gutenberg-delete-pending-change
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class DeletePaddingChange
 *
 * Cancels one Gutenberg pending item without touching target post_content.
 *
 * @since 1.0.0
 */
class DeletePaddingChange {

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
		wp_register_ability( 'wpcodex/gutenberg-delete-pending-change', [
			'label'       => __( 'Delete Gutenberg Pending Change', 'wpcodex' ),
			'description' => __( 'Cancels one Gutenberg pending item without touching target post_content. Use for per-item recovery when you need to remove one change from a batch.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'item_id' => [
						'type'        => 'integer',
						'description' => 'Gutenberg pending item id to cancel.',
					],
				],
				'required'             => [ 'item_id' ],
				'additionalProperties' => false,
			],

			'output_schema' => [ 'type' => 'object' ],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$item_id = is_scalar( $args['item_id'] ?? null ) ? (int) $args['item_id'] : 0;
				return GutenbergStorage::cancel_item( $item_id );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Cancels one queued item only. It does not alter target post_content. If all remaining items in the batch are also canceled, the batch itself becomes canceled.',
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
