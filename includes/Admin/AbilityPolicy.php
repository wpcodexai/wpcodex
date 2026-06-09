<?php
/**
 * Ability policy enforcement — unregisters abilities disabled via the Abilities Hub.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class AbilityPolicy
 *
 * Reads the per-ability option written by AbilitiesSettingsPage and
 * unregisters any disabled wpcodex/ ability from the WordPress Abilities
 * registry. Runs at a high priority inside wp_abilities_api_init so the
 * ability list is already fully populated before we prune it.
 *
 * The mcp-adapter/* abilities are never unregistered here because they
 * are hub internals.
 */
class AbilityPolicy {

	/** Option name prefix used by AbilitiesSettingsPage. */
	private const OPTION_PREFIX = 'wpcodex_ability_';

	/**
	 * Wire the late wp_abilities_api_init hook.
	 */
	public function __construct() {
		// Priority 9999 — run after all ability classes have registered.
		add_action( 'wp_abilities_api_init', [ $this, 'apply' ], 9999 );
	}

	/**
	 * Unregister all wpcodex/ abilities whose stored option is 'no'.
	 */
	public function apply(): void {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_unregister_ability' ) ) {
			return;
		}

		foreach ( wp_get_abilities() as $id => $ability ) {
			if ( ! is_string( $id ) || ! str_starts_with( $id, 'wpcodex/' ) ) {
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
