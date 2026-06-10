<?php
/**
 * Ability: wpcodex/gutenberg-get-pending-batch
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class GetPadding
 *
 * Returns compact status, item summaries, and runtime URLs for one pending batch.
 *
 * @since 1.0.0
 */
class GetPadding {

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
		wp_register_ability( 'wpcodex/gutenberg-get-pending-batch', [
			'label'       => __( 'Get Gutenberg Pending Batch', 'wpcodex' ),
			'description' => __( 'Returns compact status, item summaries, validation errors, Block Editor Queue runtime status, and curl SSE/poll URLs for one pending batch.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'batch_id' => [
						'type'        => 'integer',
						'description' => 'Gutenberg batch id.',
					],
				],
				'required'             => [ 'batch_id' ],
				'additionalProperties' => false,
			],

			'output_schema' => [ 'type' => 'object' ],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				GutenbergStorage::mark_stale_drafts();

				$batch_id = is_scalar( $args['batch_id'] ?? null ) ? (int) $args['batch_id'] : 0;
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
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => implode( "\n", [
						'Use this to inspect one batch without loading full block_spec payloads.',
						'During finalization, prefer streaming finalizer_runtime.sse_url with curl -N, or polling finalizer_runtime.poll_url with curl, until the batch is finalized, failed, or conflicted.',
						'Item status "prepared" means canonical content is staged but not yet live.',
						'If finalizer_runtime.online becomes false the user closed or lost the Block Editor Queue page; ask them to reopen finalizer_runtime.dashboard_url and keep it open before treating queued changes as live.',
					] ),
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
