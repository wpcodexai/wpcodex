<?php
/**
 * Ability: wpcodex/wpcli-run
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Runner\CliRunner;
use WPCodex\Utils\Helpers;

class WpCliRun {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/wpcli-run', [
			'label'       => __( 'Run WP-CLI', 'wpcodex' ),
			'description' => __(
				'Execute a WP-CLI command. Pass the arguments without the leading "wp" — e.g. "post list --status=publish". Returns combined stdout and stderr.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
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
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'Combined stdout + stderr from the WP-CLI process.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['command'] ) || ! is_string( $args['command'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'command must be a non-empty string.', 'wpcodex' ) );
				}
				$timeout = isset( $args['timeout'] ) ? (int) $args['timeout'] : 30;
				try {
					return CliRunner::instance()->run( $args['command'], $timeout );
				} catch ( \Throwable $e ) {
					return new \WP_Error( 'wpcodex_cli_error', $e->getMessage() );
				}
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
