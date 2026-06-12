<?php
/**
 * Ability: wpcodex/php-execute
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Runner\PhpRunner;

/**
 * Class PhpExecute
 *
 * @since 1.0.0
 */
class PhpExecute extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/php-execute';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Execute PHP', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Execute arbitrary PHP code inside the WordPress process. The full WordPress environment is available ($wpdb, all functions, all loaded plugins).', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'code' => [
					'type'        => 'string',
					'description' => 'PHP code to execute. Do not include the opening <?php tag.',
				],
			],
			'required'             => [ 'code' ],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return 'Full WordPress context is available. Use $wpdb for database queries. Return values are captured and reported in return_value. Always read before modifying — inspect current state with this ability before writing files or changing options.';
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['code'] ) || ! is_string( $input['code'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'code must be a non-empty string.', 'wpcodex' ) );
		}

		try {
			return PhpRunner::instance()->run( $input['code'] );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'wpcodex_php_runner_error', $e->getMessage() );
		}
	}
}
