<?php
/**
 * Ability: wpcodex/gutenberg-get-finalizer-runtime
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class GetFinalizerRuntime
 *
 * Reports whether the WPCodex Block Editor Queue admin page is open and heartbeating.
 *
 * @since 1.0.0
 */
class GetFinalizerRuntime {

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
		wp_register_ability( 'wpcodex/gutenberg-get-finalizer-runtime', [
			'label'       => __( 'Get Block Editor Queue Runtime', 'wpcodex' ),
			'description' => __( 'Reports whether the WPCodex Block Editor Queue admin page is open and heartbeating, including token-gated SSE and poll URLs that agents can watch with curl. Call at the start of Gutenberg work: if the runtime is offline, ask the user to open the queue dashboard and keep it open while static/native Gutenberg changes are queued and finalized.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
				'type'                 => 'object',
				'properties'           => [],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'finalizer_runtime' => [ 'type' => 'object' ],
					'user_instruction'  => [ 'type' => 'string' ],
				],
			],

			'execute_callback' => static function ( array $args ): array {
				unset( $args );
				return [
					'finalizer_runtime' => GutenbergStorage::finalizer_runtime_status(),
					'user_instruction'  => GutenbergStorage::finalizer_runtime_startup_instruction(),
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => implode( "\n", [
						'Run this once before Gutenberg content work that may queue static/native blocks.',
						'Then stream finalizer_runtime.sse_url with curl -N or poll finalizer_runtime.poll_url with curl instead of repeatedly calling MCP abilities.',
						'If finalizer_runtime.online is false, ask the user to open finalizer_runtime.dashboard_url and keep the Block Editor Queue page open while you work.',
						'During batch finalization, watch the sse_url or poll_url from the enable response; if finalizer_runtime.online becomes false, tell the user the Block Editor Queue page is offline and ask them to reopen it.',
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
