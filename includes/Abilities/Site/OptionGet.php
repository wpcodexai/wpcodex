<?php
/**
 * Ability: wpcodex/option-get
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Utils\Helpers;

class OptionGet {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/option-get', [
			'label'       => __( 'Get Option', 'wpcodex' ),
			'description' => __( 'Get a WordPress option value by name. Returns the value as JSON.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'option_name' => [
						'type'        => 'string',
						'description' => 'The WordPress option name.',
					],
					'default' => [
						'type'        => 'string',
						'description' => 'Default value if the option does not exist.',
						'default'     => '',
					],
				],
				'required'   => [ 'option_name' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'JSON-encoded option value.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['option_name'] ) || ! is_string( $args['option_name'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'option_name must be a non-empty string.', 'wpcodex' ) );
				}
				$default = isset( $args['default'] ) ? $args['default'] : '';
				$value   = get_option( sanitize_key( $args['option_name'] ), $default );
				return wp_json_encode( $value, JSON_PRETTY_PRINT ) ?: 'null';
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
