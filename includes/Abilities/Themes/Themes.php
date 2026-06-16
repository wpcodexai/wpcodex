<?php
/**
 * Theme ability aggregator.
 *
 * Registers the wpworker-astra (and future theme) ability categories and
 * returns the full list of theme ability instances for inclusion in the
 * main Abilities::create_abilities() array.
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Themes;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Abilities\Themes\Astra\FlushCache;
use WPWorker\Abilities\Themes\Astra\GetPageSettings;
use WPWorker\Abilities\Themes\Astra\GetSettings;
use WPWorker\Abilities\Themes\Astra\SetPageSettings;
use WPWorker\Abilities\Themes\Astra\UpdateSettings;

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
		add_filter( 'wpworker_abilities', [ $this, 'append' ] );
	}

	/**
	 * Appends Theme abilities to the ability list supplied by the free plugin.
	 *
	 * @since  0.1.0
	 * @param  array<int, mixed> $abilities Ability instances from WPWorker free.
	 * @return array<int, mixed>            The extended list.
	 */
	public function append( array $abilities ): array {
		foreach ( self::create_theme_abilities() as $ability ) {
			$abilities[] = $ability;
		}
		return $abilities;
	}
	/**
	 * Append theme ability instances to the filtered abilities list.
	 *
	 * Abilities for inactive themes still return a helpful WP_Error when the
	 * theme is not active, rather than being silently skipped.
	 *
	 * @param  AbstractAbility[] $abilities Existing abilities passed by the filter.
	 * @return AbstractAbility[]
	 */
	private static function create_theme_abilities(): array {
		return [
			// Astra abilities.
			new GetSettings(),
			new UpdateSettings(),
			new GetPageSettings(),
			new SetPageSettings(),
			new FlushCache(),
		] ;
	}
}
