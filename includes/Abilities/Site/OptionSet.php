<?php
/**
 * Ability: wpcodex/option-set
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Abilities\AbstractAbility;

/**
 * Class OptionSet
 *
 * @since 1.0.0
 */
class OptionSet extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/option-set';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Set Option', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Set a WordPress option value. The value is stored as a string.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'Success or unchanged message.',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string|\WP_Error {
		if ( empty( $input['option_name'] ) || ! is_string( $input['option_name'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'option_name must be a non-empty string.', 'wpcodex' ) );
		}
		if ( ! isset( $input['option_value'] ) || ! is_string( $input['option_value'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'option_value must be a string.', 'wpcodex' ) );
		}
		$name     = sanitize_key( $input['option_name'] );
		$value    = $input['option_value'];
		$autoload = isset( $input['autoload'] ) ? (bool) $input['autoload'] : false;
		$updated  = update_option( $name, $value, $autoload );
		return $updated
			? sprintf( 'Option "%s" updated.', esc_html( $name ) )
			: sprintf( 'Option "%s" unchanged (value may already be set).', esc_html( $name ) );
	}
}
