<?php
/**
 * Ability: wpworker/option-get
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Site;

use WPWorker\Abilities\AbstractAbility;

/**
 * Class OptionGet
 *
 * @since 1.0.0
 */
class OptionGet extends AbstractAbility {
	public function get_category(): string {
		return 'wpworker-site';
	}
	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/option-get';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Get Option', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Get a WordPress option value by name. Returns the value as JSON.', 'worker-ai' );
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
			return new \WP_Error( 'wpworker_invalid_input', __( 'option_name must be a non-empty string.', 'worker-ai' ) );
		}
		$default = $input['default'] ?? '';
		$value   = get_option( sanitize_key( $input['option_name'] ), $default );
		return wp_json_encode( $value, JSON_PRETTY_PRINT ) ?: 'null';
	}
}
