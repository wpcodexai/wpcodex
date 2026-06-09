<?php
/**
 * Ability: wpcodex/php-execute
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Runner\PhpRunner;
use WPCodex\Utils\Helpers;

/**
 * Class PhpExecute
 */
class PhpExecute {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/php-execute', [
			'label'       => __( 'Execute PHP', 'wpcodex' ),
			'description' => __( 'Execute arbitrary PHP code inside the WordPress process. The full WordPress environment is available ($wpdb, all functions, all loaded plugins).', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'code' => [
						'type'        => 'string',
						'description' => 'PHP code to execute. Do not include the opening <?php tag.',
					],
				],
				'required'             => [ 'code' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'           => [ 'type' => 'boolean', 'description' => 'True when execution completed without a thrown exception.' ],
					'return_value'      => [ 'description' => 'Value returned by the code (if any).' ],
					'output'            => [ 'type' => 'string', 'description' => 'Captured stdout (echo / print output).' ],
					'errors'            => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'type'    => [ 'type' => 'string' ],
								'message' => [ 'type' => 'string' ],
								'file'    => [ 'type' => 'string' ],
								'line'    => [ 'type' => 'integer' ],
							],
						],
						'description' => 'Non-fatal errors, warnings, and notices captured during execution.',
					],
					'error_message'     => [ 'type' => 'string', 'description' => 'Exception message when success is false.' ],
					'error_class'       => [ 'type' => 'string', 'description' => 'Exception class name when success is false.' ],
					'execution_time_ms' => [ 'type' => 'number', 'description' => 'Wall-clock execution time in milliseconds.' ],
				],
				'required' => [ 'success', 'output', 'errors', 'error_message', 'error_class', 'execution_time_ms' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['code'] ) || ! is_string( $args['code'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'code must be a non-empty string.', 'wpcodex' ) );
				}

				try {
					return PhpRunner::instance()->run( $args['code'] );
				} catch ( \RuntimeException $e ) {
					return new \WP_Error( 'wpcodex_php_runner_error', $e->getMessage() );
				}
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Full WordPress context is available. Use $wpdb for database queries. Return values are captured and reported in return_value. Always read before modifying — inspect current state with this ability before writing files or changing options.',
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
