<?php
/**
 * Ability: allyworker/astra-update-settings
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Themes\Astra;

use AllyWorker\Abilities\AbstractAbility;

/**
 * Class UpdateSettings
 *
 * Merge one or more key→value pairs into the `astra-settings` option and
 * optionally flush Astra's dynamic CSS cache so the changes take effect
 * immediately.
 *
 * @since 1.0.0
 */
class UpdateSettings extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/astra-update-settings';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Astra: Update Settings', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Merge one or more Astra customizer settings into the astra-settings option. '
			. 'Only the keys you supply are changed; everything else is preserved. '
			. 'Pass flush_cache: true (default) to regenerate the dynamic CSS immediately.',
			'allyworker'
		);
	}

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-themes';
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return <<<'INSTR'
Pass any valid Astra setting key in "settings". Values must match Astra's expected type:
  - Scalar strings / ints for most settings (e.g. "primary-color": "#0000ff")
  - Objects for responsive values (e.g. "body-font-size": {"desktop":16,"tablet":15,"mobile":14})
  - Objects for header/footer builder rows (hb-row-1, fb-row-1, …) — use astra-get-settings first
    to read the current structure before overwriting builder rows.
Always use flush_cache: true (the default) unless you are batching multiple update calls.
After the last batch call, call allyworker/astra-flush-cache manually.
INSTR;
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'settings'    => [
					'type'                 => 'object',
					'description'          => 'Key→value pairs to merge into astra-settings. Nested objects are shallow-merged at the top level.',
					'additionalProperties' => true,
				],
				'flush_cache' => [
					'type'        => 'boolean',
					'description' => 'Flush Astra dynamic CSS cache after saving. Default: true.',
					'default'     => true,
				],
			],
			'required'             => [ 'settings' ],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'updated'     => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Keys that were changed.',
				],
				'unchanged'   => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Keys whose value was already identical.',
				],
				'cache_flushed' => [
					'type'        => 'boolean',
					'description' => 'Whether the Astra CSS cache was flushed.',
				],
			],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( ! GetSettings::astra_is_active() ) {
			return new \WP_Error( 'allyworker_astra_inactive', __( 'The Astra theme is not currently active.', 'allyworker' ) );
		}

		if ( ! isset( $input['settings'] ) || ! is_array( $input['settings'] ) ) {
			return new \WP_Error( 'allyworker_invalid_input', __( 'settings must be an object.', 'allyworker' ) );
		}

		/** @var mixed $raw */
		$raw      = get_option( 'astra-settings', [] );
		$current  = is_array( $raw ) ? $raw : [];
		$incoming = $input['settings'];

		$updated   = [];
		$unchanged = [];

		foreach ( $incoming as $key => $value ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}
			// Loose comparison — Astra stores mixed types (strings, ints, arrays).
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( array_key_exists( $key, $current ) && $current[ $key ] == $value ) {
				$unchanged[] = $key;
				continue;
			}
			$current[ $key ] = $value;
			$updated[]       = $key;
		}

		if ( $updated !== [] ) {
			update_option( 'astra-settings', $current, false );
		}

		$flush        = ! array_key_exists( 'flush_cache', $input ) || true === $input['flush_cache'];
		$cache_flushed = false;
		if ( $flush ) {
			$cache_flushed = FlushCache::flush();
		}

		return [
			'updated'      => $updated,
			'unchanged'    => $unchanged,
			'cache_flushed' => $cache_flushed,
		];
	}
}
