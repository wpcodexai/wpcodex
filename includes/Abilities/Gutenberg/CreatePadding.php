<?php
/**
 * Ability: wpcodex/gutenberg-create-pending-batch
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class CreatePadding
 *
 * Creates an empty draft Gutenberg batch.
 *
 * @since 1.0.0
 */
class CreatePadding {

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
		wp_register_ability( 'wpcodex/gutenberg-create-pending-batch', [
			'label'       => __( 'Create Gutenberg Pending Batch', 'wpcodex' ),
			'description' => __( 'Creates an empty draft Gutenberg batch. Add changes with wpcodex/gutenberg-add-pending-change, then enable finalization with wpcodex/gutenberg-enable-batch-finalization.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'label'            => [
						'type'        => 'string',
						'description' => 'Short human-readable label for this batch.',
					],
					'agent_label'      => [
						'type'        => 'string',
						'description' => 'Identifies which agent is making this batch (e.g. "Claude Sonnet 4").',
					],
					'agent_session_id' => [
						'type'        => 'string',
						'description' => 'Session / conversation ID of the originating agent.',
					],
					'agent_note'       => [
						'type'        => 'string',
						'description' => 'Longer note for the user about what this batch accomplishes.',
					],
				],
				'additionalProperties' => false,
			],

			'output_schema' => [ 'type' => 'object' ],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				GutenbergStorage::mark_stale_drafts();

				$label            = is_string( $args['label'] ?? null ) ? $args['label'] : '';
				$agent_label      = is_string( $args['agent_label'] ?? null ) ? $args['agent_label'] : '';
				$agent_session_id = is_string( $args['agent_session_id'] ?? null ) ? $args['agent_session_id'] : '';
				$agent_note       = is_string( $args['agent_note'] ?? null ) ? $args['agent_note'] : '';

				$batch_id = GutenbergStorage::create_batch( $label, $agent_label, $agent_session_id, $agent_note );
				if ( is_wp_error( $batch_id ) ) {
					return $batch_id;
				}

				$batch = GutenbergStorage::find_batch( $batch_id );
				if ( ! $batch instanceof \WP_Post ) {
					return new \WP_Error( 'gutenberg_batch_not_found', sprintf( 'Gutenberg batch %d was not found after creation.', $batch_id ) );
				}

				$runtime = GutenbergStorage::finalizer_runtime_status();

				return [
					'batch_id'              => $batch->ID,
					'batch_status'          => GutenbergStorage::gb_status( $batch->ID ),
					'finalization_required' => true,
					'finalizer_runtime'     => $runtime,
					'user_instruction'      => GutenbergStorage::finalizer_runtime_startup_instruction(),
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Creates an empty draft Gutenberg batch. Then call wpcodex/gutenberg-add-pending-change one or more times to populate it, and finally wpcodex/gutenberg-enable-batch-finalization to unlock finalization. Check finalizer_runtime.online and ask the user to open finalizer_runtime.dashboard_url if it is offline before static/native Gutenberg content is queued.',
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
