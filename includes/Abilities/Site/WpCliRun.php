<?php
/**
 * Ability: wpcodex/wpcli-run
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Runner\CliRunner;

/**
 * Class WpCliRun
 *
 * @since 1.0.0
 */
class WpCliRun extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/wpcli-run';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Run WP-CLI', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Execute a WP-CLI command. Pass the arguments without the leading "wp" — e.g. "post list --status=publish". Returns combined stdout and stderr.',
			'wpcodex'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'command' => [
					'type'        => 'string',
					'description' => 'WP-CLI command arguments (without the leading "wp").',
				],
				'timeout' => [
					'type'        => 'integer',
					'description' => 'Max execution time in seconds. Default: 30.',
					'default'     => 30,
				],
			],
			'required'   => [ 'command' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'Combined stdout + stderr from the WP-CLI process.',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string|\WP_Error {
		if ( empty( $input['command'] ) || ! is_string( $input['command'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'command must be a non-empty string.', 'wpcodex' ) );
		}
		$timeout = isset( $input['timeout'] ) ? (int) $input['timeout'] : 30;
		try {
			return CliRunner::instance()->run( $input['command'], $timeout );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'wpcodex_cli_error', $e->getMessage() );
		}
	}
}
