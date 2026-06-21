<?php
/**
 * Ability: allyworker/astra-flush-cache
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Themes\Astra;

use AllyWorker\Abilities\AbstractAbility;

/**
 * Class FlushCache
 *
 * Delete all Astra-generated dynamic CSS transients and object-cache entries
 * so that changes to astra-settings take effect immediately on the next page load.
 *
 * @since 1.0.0
 */
class FlushCache extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/astra-flush-cache';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Astra: Flush Cache', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Clear Astra\'s dynamic CSS cache (transients, option-based cache, and file cache) '
			. 'so that any changes made to astra-settings or page meta take effect immediately. '
			. 'Always call this after updating Astra settings if flush_cache was set to false.',
			'allyworker'
		);
	}

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-themes';
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [],
			'required'             => [],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'cleared'  => [ 'type' => 'integer', 'description' => 'Number of transient / cache entries cleared.' ],
				'messages' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Detail log.' ],
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

		$cleared  = 0;
		$messages = [];

		$flushed  = self::flush();
		$cleared += $flushed ? 1 : 0;
		$messages[] = $flushed
			? 'Astra dynamic CSS cache flushed.'
			: 'Astra native cache flush not available (astra_cache_flush not found); manual transient deletion performed.';

		return [
			'cleared'  => $cleared,
			'messages' => $messages,
		];
	}

	/**
	 * Flush all Astra caches.
	 *
	 * Called as a static helper from UpdateSettings so both paths share one implementation.
	 *
	 * @return bool True when at least one cache mechanism was triggered.
	 */
	public static function flush(): bool {
		$flushed = false;

		// 1. Use Astra's own cache-flush function when available (Astra 3.x+).
		if ( function_exists( 'astra_cache_flush' ) ) {
			astra_cache_flush();
			$flushed = true;
		}

		// 2. Delete well-known Astra transient keys.
		$known_transients = [
			'astra_theme_css_option_data',
			'astra_theme_variation',
			'astra_addon_css_cache',
			'astra_get_dynamic_css_cache',
		];

		$stylesheet = get_stylesheet();
		$known_transients[] = 'astra_addon_css_cache_' . $stylesheet;
		$known_transients[] = 'astra_theme_css_' . $stylesheet;

		foreach ( $known_transients as $transient ) {
			delete_transient( $transient );
		}

		// 3. Bulk-delete any remaining Astra transients from the options table.
		global $wpdb;

		$deleted = (int) $wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '\_transient\_astra\_%'
			   OR option_name LIKE '\_transient\_timeout\_astra\_%'"
		);

		if ( $deleted > 0 ) {
			$flushed = true;
		}

		// 4. Clear object cache for the astra group when possible.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'astra-theme' );
		}

		return $flushed;
	}
}
