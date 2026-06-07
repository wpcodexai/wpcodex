<?php
/**
 * Ability: wpcodex/gutenberg-list-pending-batches
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class ListPadding
 *
 * Lists compact queue state grouped by Gutenberg batch plus current Block Editor Queue runtime status.
 *
 * @since 1.0.0
 */
class ListPadding {

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
		wp_register_ability( 'wpcodex/gutenberg-list-pending-batches', [
			'label'       => __( 'List Gutenberg Pending Batches', 'wpcodex' ),
			'description' => __( 'Lists compact queue state grouped by Gutenberg batch for agent recovery, plus the current Block Editor Queue runtime status and curl SSE/poll URLs. Full block specs are not returned.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
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
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'batches'           => [ 'type' => 'array' ],
					'finalizer_runtime' => [ 'type' => 'object' ],
					'user_instruction'  => [ 'type' => 'string' ],
				],
			],

			'execute_callback' => static function ( array $args ): array {
				GutenbergStorage::mark_stale_drafts();

				$status   = is_scalar( $args['status'] ?? null ) ? (string) $args['status'] : '';
				$limit    = max( 1, min( 100, is_scalar( $args['limit'] ?? null ) ? (int) $args['limit'] : 20 ) );
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
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => implode( "\n", [
						'Use this for compact recovery/discovery.',
						'The top-level finalizer_runtime tells you whether the Block Editor Queue page is currently open and includes sse_url/poll_url for curl loops.',
						'If it is offline during Gutenberg work, ask the user to reopen dashboard_url and keep it open.',
						'For one batch, call wpcodex/gutenberg-get-pending-batch, then watch the returned sse_url with curl -N or poll_url with curl.',
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
