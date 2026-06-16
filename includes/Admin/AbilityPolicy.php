<?php
/**
 * Ability policy enforcement — unregisters abilities disabled via the Abilities Hub.
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Admin;

/**
 * Class AbilityPolicy
 *
 * Reads the per-ability option written by AbilitiesSettingsPage and
 * unregisters any disabled wpworker/ ability from the WordPress Abilities
 * registry. Runs at a high priority inside wp_abilities_api_init so the
 * ability list is already fully populated before we prune it.
 *
 * The mcp-adapter/* abilities are never unregistered here because they
 * are hub internals.
 *
 * @since 1.0.0
 */
class AbilityPolicy {

	/**
	 * Option name prefix used by AbilitiesSettingsPage.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private const OPTION_PREFIX = 'wpworker_ability_';

	/**
	 * Wires the late wp_abilities_api_init hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Priority 9999 — run after all ability classes have registered.
		add_action( 'wp_abilities_api_init', [ $this, 'apply' ], 9999 );
	}

	/**
	 * Checks whether a specific ability is currently enabled.
	 *
	 * Reads the same option that AbilitiesSettingsPage writes and that apply()
	 * acts on. Pure read — no side effects.
	 *
	 * @since  1.0.0
	 * @param  string $ability_name Full ability name, e.g. 'wpworker/file-read'.
	 * @return bool True if the ability is enabled; false if explicitly disabled.
	 */
	public static function is_enabled( string $ability_name ): bool {
		$option_key = self::OPTION_PREFIX . sanitize_key( str_replace( '/', '_', $ability_name ) );
		return get_option( $option_key, 'yes' ) !== 'no';
	}

	/**
	 * Unregisters all wpworker/ abilities whose stored option is 'no'.
	 *
	 * @since 1.0.0
	 */
	public function apply(): void {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_unregister_ability' ) ) {
			return;
		}

		foreach ( wp_get_abilities() as $id => $ability ) {
			if ( ! is_string( $id ) || ! str_starts_with( $id, 'wpworker/' ) ) {
				continue;
			}

			$option_key = self::OPTION_PREFIX . sanitize_key( str_replace( '/', '_', $id ) );

			// Default is enabled ('yes'); we only act when explicitly 'no'.
			if ( get_option( $option_key, 'yes' ) === 'no' ) {
				wp_unregister_ability( $id );
			}
		}
	}
}
