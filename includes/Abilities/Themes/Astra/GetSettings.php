<?php
/**
 * Ability: wpworker/astra-get-settings
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Themes\Astra;

use WPWorker\Abilities\AbstractAbility;

/**
 * Class GetSettings
 *
 * Read the Astra theme's global customizer settings stored in the
 * `astra-settings` option.  Pass an optional `keys` array to retrieve only
 * specific settings; omit it to receive the full settings object.
 *
 * @since 1.0.0
 */
class GetSettings extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/astra-get-settings';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Astra: Get Settings', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Read the Astra theme global customizer settings from the astra-settings option. '
			. 'Pass a "keys" array to retrieve specific settings; omit to get everything. '
			. 'Returns a JSON object. Use wpworker/astra-update-settings to change values.',
			'worker-ai'
		);
	}

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-themes';
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return <<<'INSTR'
Common Astra setting keys (sample):
  Colors:     primary-color, link-color, text-color, heading-color, background-color
  Typography: body-font-family, body-font-weight, body-font-size (object: {desktop,tablet,mobile}),
              heading-font-family, h1-font-size … h6-font-size
  Layout:     site-content-width (px int), content-layout (full-width|boxed|content-boxed|narrow-container),
              blog-layout (1|2|3), site-sidebar-layout (default|left-sidebar|right-sidebar|no-sidebar)
  Header:     header-layouts (1–6), header-main-rt-section (search|button|none)
  Footer:     footer-copyright (HTML string)
  Buttons:    button-color, button-h-color, button-bg-color, button-h-bg-color, button-border-radius (px)
Call wpworker/astra-flush-cache after any change to regenerate the dynamic CSS.
INSTR;
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'keys' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Optional list of setting keys to return. Omit to return all settings.',
				],
			],
			'required'             => [],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'JSON-encoded Astra settings object (full or filtered subset).',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string|\WP_Error {
		if ( ! self::astra_is_active() ) {
			return new \WP_Error( 'wpworker_astra_inactive', __( 'The Astra theme is not currently active.', 'worker-ai' ) );
		}

		/** @var mixed $raw */
		$raw      = get_option( 'astra-settings', [] );
		$settings = is_array( $raw ) ? $raw : [];

		$keys = isset( $input['keys'] ) && is_array( $input['keys'] ) ? $input['keys'] : [];
		$keys = array_filter( $keys, static fn( mixed $k ): bool => is_string( $k ) && $k !== '' );
		$keys = array_values( $keys );

		if ( $keys !== [] ) {
			$subset = [];
			foreach ( $keys as $key ) {
				$subset[ $key ] = $settings[ $key ] ?? null;
			}
			return wp_json_encode( $subset, JSON_PRETTY_PRINT ) ?: '{}';
		}

		return wp_json_encode( $settings, JSON_PRETTY_PRINT ) ?: '{}';
	}

	/**
	 * Check whether Astra is the active theme (template or stylesheet).
	 */
	public static function astra_is_active(): bool {
		$theme = wp_get_theme();
		return in_array( 'astra', [ $theme->get_stylesheet(), $theme->get_template() ], true );
	}
}
