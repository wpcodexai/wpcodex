<?php
/**
 * Ability registration aggregator.
 *
 * Instantiates every built-in ability and registers them all at once.
 * Pro plugins extend the list via the 'wpworker_abilities' filter:
 *
 *   add_filter( 'wpworker_abilities', function ( array $abilities ): array {
 *       $abilities[] = new \MyProPlugin\Abilities\MyProAbility();
 *       return $abilities;
 *   } );
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Abilities;

use WPWorker\Admin\AbilityPolicy;
use WPWorker\Abilities\Core;
use WPWorker\Abilities\Files;
use WPWorker\Abilities\Gutenberg;
use WPWorker\Abilities\Site;
use WPWorker\Abilities\Skills;
use WPWorker\Abilities\Themes;


/**
 * Class Abilities
 *
 * @since 1.1.0
 */
class Abilities {

	/**
	 * Full ability index built during register(), keyed by ability name.
	 *
	 * Stored in a static so AbilitiesSettingsPage can read it in the same
	 * request without a DB round-trip. Includes disabled abilities that are
	 * excluded from wp_register_ability() but must remain visible in the UI.
	 *
	 * @since 1.1.0
	 * @var   array<string, array<string, string>>
	 */
	private static array $all_abilities = [];

	/**
	 * Sets up the wp_abilities_api_init registration hook.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		// Priority 20 — must run after the MCP adapter's priority-10 registration
		// so mcp-adapter/discover-abilities already exists when DiscoverAbilities
		// unregisters and replaces it.
		add_action( 'wp_abilities_api_init', [ $this, 'register' ], 20 );
		add_action( 'init', [ $this, 'add_theme_and_plugin_abilities' ] );
	}

	/**
	 * Returns the full flat ability index, building it on-demand if needed.
	 *
	 * Includes both enabled and disabled abilities so the settings page can
	 * always display every known ability and let the admin re-enable any of them.
	 *
	 * When called during a normal admin page load (before wp_abilities_api_init
	 * has fired), the index is built directly from create_abilities() so the
	 * settings page never shows an empty list.
	 *
	 * @since  1.1.0
	 * @return array<string, array<string, string>>
	 */
	public static function get_all_ability_data(): array {
		if ( empty( self::$all_abilities ) ) {
			/** @var AbstractAbility[] $abilities */
			$abilities = apply_filters( 'wpworker_abilities', static::create_abilities() );
			foreach ( $abilities as $ability ) {
				self::$all_abilities[ $ability->get_name() ] = [
					'id'          => $ability->get_name(),
					'label'       => $ability->get_label(),
					'description' => $ability->get_description(),
					'category'    => $ability->get_category(),
				];
			}
		}
		return self::$all_abilities;
	}

	/**
	 * Registers all built-in and filtered abilities.
	 *
	 * Before registering, populates the static $all_abilities index with every
	 * ability (enabled and disabled) so the settings page can display them all
	 * regardless of their enabled/disabled state.
	 *
	 * The 'wpworker_abilities' filter lets pro plugins append their own ability
	 * instances without touching this file.
	 *
	 * @since 1.1.0
	 */
	public function register(): void {
		/** @var AbstractAbility[] $abilities */
		$abilities = apply_filters( 'wpworker_abilities', static::create_abilities() );

		// Rebuild the static index on every invocation so it reflects the
		// current filter output (e.g. pro-plugin additions).
		self::$all_abilities = [];
		foreach ( $abilities as $ability ) {
			self::$all_abilities[ $ability->get_name() ] = [
				'id'          => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
				'category'    => $ability->get_category(),
			];
		}

		foreach ( $abilities as $ability ) {
			if ( $this->should_register( $ability ) ) {
				$ability->register();
			}
		}
	}

	/**
	 * Determines whether an ability should be registered.
	 *
	 * Reads the wpworker_ability_* option set in AbilityPolicy (the admin
	 * Enable/Disable toggle). Abilities are enabled by default.
	 *
	 * @since  1.1.0
	 * @param  AbstractAbility $ability The ability instance to check.
	 * @return bool True if the ability is enabled; false if explicitly disabled.
	 */
	private function should_register( AbstractAbility $ability ): bool {
		return AbilityPolicy::is_enabled( $ability->get_name() );
	}

	/**
	 * Returns the complete list of built-in ability instances.
	 *
	 * Static so get_all_ability_data() can build the index on admin page
	 * loads without constructing a registering Abilities instance. The
	 * wpworker_abilities filter is still applied by every caller, so
	 * third-party abilities added via the filter are always included.
	 *
	 * @since  1.1.0
	 * @return AbstractAbility[]
	 */
	public static function create_abilities(): array {
		return [
			// Core.
			new Core\DiscoverAbilities(),

			// File abilities.
			new Files\FileRead(),
			new Files\FileList(),
			new Files\FileDisable(),
			new Files\FileEnable(),
			new Files\CreateUploadLink(),

			// Site / WordPress abilities.
			new Site\SiteInfo(),
			new Site\PostQuery(),
			new Site\OptionGet(),
			new Site\OptionSet(),
			new Site\CreateAdminAccessLink(),

			// Skills abilities.
			new Skills\SkillList(),
			new Skills\SkillRead(),
			new Skills\SkillCreate(),
			new Skills\SkillUpdate(),
			new Skills\SkillDelete(),
			new Skills\SkillListRevisions(),
			new Skills\SkillRestoreRevision(),

			// Gutenberg / Block Editor Queue abilities.
			new Gutenberg\GetContent(),
			new Gutenberg\WriteContent(),
			new Gutenberg\GetFinalizationUrl(),
			new Gutenberg\CreatePadding(),
			new Gutenberg\AddPaddingChange(),
			new Gutenberg\EnableFinalization(),
			new Gutenberg\DeletePadding(),
			new Gutenberg\DeletePaddingChange(),
			new Gutenberg\GetPadding(),
			new Gutenberg\ListPadding(),
			new Gutenberg\GetFinalizerRuntime()
		];
	}
	
	public function add_theme_and_plugin_abilities(): void {
		new Themes\Themes();
		/**
		 * Fires during the 'init' action after all core abilities have been registered.
		 *
		 * Themes and plugins can hook into this to register their own abilities
		 * after the core ones, ensuring their abilities are available in the same
		 * request and appear in the settings page without needing a separate DB
		 * round-trip.
		 *
		 * @since 1.1.0
		 */
		do_action( 'wpworker_theme_abilities' );
		do_action( 'wpworker_plugin_abilities' );
	}
}
