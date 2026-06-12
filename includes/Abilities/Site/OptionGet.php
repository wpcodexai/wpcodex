<?php
/**
 * Ability: wpcodex/option-get
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Abilities\AbstractAbility;

/**
 * Class OptionGet
 *
 * @since 1.0.0
 */
class OptionGet extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/option-get';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Get Option', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Get a WordPress option value by name. Returns the value as JSON.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'JSON-encoded option value.',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string|\WP_Error {
		if ( empty( $input['option_name'] ) || ! is_string( $input['option_name'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'option_name must be a non-empty string.', 'wpcodex' ) );
		}
		$default = $input['default'] ?? '';
		$value   = get_option( sanitize_key( $input['option_name'] ), $default );
		return wp_json_encode( $value, JSON_PRETTY_PRINT ) ?: 'null';
	}
}
