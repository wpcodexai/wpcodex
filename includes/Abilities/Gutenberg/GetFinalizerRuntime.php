<?php
/**
 * Ability: allyworker/gutenberg-get-finalizer-runtime
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Gutenberg;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Utils\GutenbergStorage;

/**
 * Class GetFinalizerRuntime
 *
 * Reports whether the AllyWorker Block Editor Queue admin page is open and heartbeating.
 *
 * @since 1.0.0
 */
class GetFinalizerRuntime extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/gutenberg-get-finalizer-runtime';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Get Block Editor Queue Runtime', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Reports whether the AllyWorker Block Editor Queue admin page is open and heartbeating, including token-gated SSE and poll URLs that agents can watch with curl. Call at the start of Gutenberg work: if the runtime is offline, ask the user to open the queue dashboard and keep it open while static/native Gutenberg changes are queued and finalized.', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
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
			'Run this once before Gutenberg content work that may queue static/native blocks.',
			'Then stream finalizer_runtime.sse_url with curl -N or poll finalizer_runtime.poll_url with curl instead of repeatedly calling MCP abilities.',
			'If finalizer_runtime.online is false, ask the user to open finalizer_runtime.dashboard_url and keep the Block Editor Queue page open while you work.',
			'During batch finalization, watch the sse_url or poll_url from the enable response; if finalizer_runtime.online becomes false, tell the user the Block Editor Queue page is offline and ask them to reopen it.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array {
		unset( $input );
		return [
			'finalizer_runtime' => GutenbergStorage::finalizer_runtime_status(),
			'user_instruction'  => GutenbergStorage::finalizer_runtime_startup_instruction(),
		];
	}
}
