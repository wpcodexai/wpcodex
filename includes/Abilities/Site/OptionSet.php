<?php
/**
 * Ability: wpcodex/option-set
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Utils\Helpers;

class OptionSet {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/option-set', [
			'label'       => __( 'Set Option', 'wpcodex' ),
			'description' => __( 'Set a WordPress option value. The value is stored as a string.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'option_name'  => [
						'type'        => 'string',
						'description' => 'The WordPress option name.',
					],
					'option_value' => [
						'type'        => 'string',
						'description' => 'The value to store.',
					],
					'autoload' => [
						'type'        => 'boolean',
						'description' => 'Whether to autoload this option. Default: false.',
						'default'     => false,
					],
				],
				'required'   => [ 'option_name', 'option_value' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'Success or unchanged message.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['option_name'] ) || ! is_string( $args['option_name'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'option_name must be a non-empty string.', 'wpcodex' ) );
				}
				if ( ! isset( $args['option_value'] ) || ! is_string( $args['option_value'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'option_value must be a string.', 'wpcodex' ) );
				}
				$name     = sanitize_key( $args['option_name'] );
				$value    = $args['option_value'];
				$autoload = isset( $args['autoload'] ) ? (bool) $args['autoload'] : false;
				$updated  = update_option( $name, $value, $autoload );
				return $updated
					? sprintf( 'Option "%s" updated.', esc_html( $name ) )
					: sprintf( 'Option "%s" unchanged (value may already be set).', esc_html( $name ) );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
