<?php
/**
 * Theme ability aggregator.
 *
 * Registers the wpcodex-astra (and future theme) ability categories and
 * returns the full list of theme ability instances for inclusion in the
 * main Abilities::create_abilities() array.
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Themes;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Abilities\Themes\Astra\FlushCache;
use WPCodex\Abilities\Themes\Astra\GetPageSettings;
use WPCodex\Abilities\Themes\Astra\GetSettings;
use WPCodex\Abilities\Themes\Astra\SetPageSettings;
use WPCodex\Abilities\Themes\Astra\UpdateSettings;

/**
 * Class Themes
 *
 * Registers theme-specific ability categories via wp_abilities_api_init
 * (priority 5, before the main Abilities class registers its abilities at 20).
 *
 * @since 1.0.0
 */
class Themes {

	/**
	 * Wire the category registration hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', [ $this, 'register_categories' ], 5 );
		add_filter( 'wp_codex_abilities', [ $this, 'add_abilities' ] );
	}

	/**
	 * Register ability categories for every supported theme.
	 *
	 * @since 1.0.0
	 */
	public function register_categories(): void {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category( 'wpcodex-themes', [
				'label'       => __( 'Themes', 'wpcodex' ),
				'description' => __( 'Abilities for reading and updating theme settings globally and per page.', 'wpcodex' ),
			] );
		}	
	}

	/**
	 * Append theme ability instances to the filtered abilities list.
	 *
	 * Merged into the main Abilities::add_abilities() list.
	 * Abilities for inactive themes still return a helpful WP_Error when the
	 * theme is not active, rather than being silently skipped.
	 *
	 * @param  AbstractAbility[] $abilities Existing abilities passed by the filter.
	 * @return AbstractAbility[]
	 */
	public function add_abilities( array $abilities ): array {
		return array_merge( $abilities, [
			// Astra abilities.
			new GetSettings(),
			new UpdateSettings(),
			new GetPageSettings(),
			new SetPageSettings(),
			new FlushCache(),
		] );
	}
}
