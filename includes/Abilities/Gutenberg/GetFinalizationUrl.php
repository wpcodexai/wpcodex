<?php
/**
 * Ability: wpcodex/gutenberg-get-finalization-url
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class GetFinalizationUrl
 *
 * Returns the Block Editor Queue URL and current batch shape for a pending Gutenberg batch.
 *
 * @since 1.0.0
 */
class GetFinalizationUrl {

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
		wp_register_ability( 'wpcodex/gutenberg-get-finalization-url', [
			'label'       => __( 'Get Gutenberg Finalization URL', 'wpcodex' ),
			'description' => __( 'Returns the Block Editor Queue URL and current batch shape for a pending Gutenberg batch. The user opens the queue page and the browser JS finalizer processes the queued block changes automatically.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'batch_id' => [
						'type'        => 'integer',
						'description' => 'Gutenberg batch id returned by wpcodex/gutenberg-write-content or wpcodex/gutenberg-enable-batch-finalization.',
					],
				],
				'required' => [ 'batch_id' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'batch_id'          => [ 'type' => 'integer' ],
					'batch_status'      => [ 'type' => 'string' ],
					'finalization_url'  => [ 'type' => 'string', 'description' => 'Open this in a browser to apply the queued Gutenberg changes.' ],
					'finalizer_runtime' => [ 'type' => 'object' ],
					'user_instruction'  => [ 'type' => 'string' ],
				],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$batch_id = is_scalar( $args['batch_id'] ?? null ) ? (int) $args['batch_id'] : 0;
				if ( $batch_id <= 0 ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'batch_id must be a positive integer.', 'wpcodex' ) );
				}

				$batch = GutenbergStorage::find_batch( $batch_id );
				if ( ! $batch instanceof \WP_Post ) {
					return new \WP_Error(
						'wpcodex_not_found',
						/* translators: %d batch ID */
						sprintf( __( 'Gutenberg batch %d was not found.', 'wpcodex' ), $batch_id )
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
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => implode( "\n", [
						'Call after wpcodex/gutenberg-write-content or wpcodex/gutenberg-enable-batch-finalization to get the finalization link.',
						'Share finalization_url with the user — they open the Block Editor Queue page which processes the blocks automatically.',
						'Stream finalizer_runtime.sse_url with curl -N or poll poll_url until the batch is finalized, failed, or conflicted.',
						'Do not treat the changes as live until finalization completes.',
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
