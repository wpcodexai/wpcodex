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

class PhpExecute {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/php-execute', [
			'label'       => __( 'Execute PHP', 'wpcodex' ),
			'description' => __(
				'Execute arbitrary PHP code inside the WordPress process. Returns printed output and any return value. Code runs with full access to $wpdb, all WordPress functions, and loaded plugins.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'code' => [
						'type'        => 'string',
						'description' => 'Valid PHP code to execute. Do not include an opening <?php tag.',
					],
				],
				'required'   => [ 'code' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'Captured stdout output plus any serialised return value.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['code'] ) || ! is_string( $args['code'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'code must be a non-empty string.', 'wpcodex' ) );
				}
				return PhpRunner::instance()->run( $args['code'] );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
