<?php
/**
 * Ability: wpworker/gutenberg-create-pending-batch
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Gutenberg;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Utils\GutenbergStorage;

/**
 * Class CreatePadding
 *
 * Creates an empty draft Gutenberg batch.
 *
 * @since 1.0.0
 */
class CreatePadding extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/gutenberg-create-pending-batch';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Create Gutenberg Pending Batch', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Creates an empty draft Gutenberg batch. Add changes with wpworker/gutenberg-add-pending-change, then enable finalization with wpworker/gutenberg-enable-batch-finalization.', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [ 'type' => 'object' ];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return 'Creates an empty draft Gutenberg batch. Then call wpworker/gutenberg-add-pending-change one or more times to populate it, and finally wpworker/gutenberg-enable-batch-finalization to unlock finalization. Check finalizer_runtime.online and ask the user to open finalizer_runtime.dashboard_url if it is offline before static/native Gutenberg content is queued.';
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		GutenbergStorage::mark_stale_drafts();

		$label            = is_string( $input['label'] ?? null ) ? $input['label'] : '';
		$agent_label      = is_string( $input['agent_label'] ?? null ) ? $input['agent_label'] : '';
		$agent_session_id = is_string( $input['agent_session_id'] ?? null ) ? $input['agent_session_id'] : '';
		$agent_note       = is_string( $input['agent_note'] ?? null ) ? $input['agent_note'] : '';

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
	}
}
