<?php
/**
 * Ability registration aggregator.
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;

class Abilities {

	public function __construct() {
		add_action( 'wp_abilities_api_init', [ $this, 'register' ] );
	}
	
	/**
	 * \Register all abilities. Each ability is responsible for its own registration, so this just calls init() on each.
	 * New abilities should be added here.
	 *
	 */
	public  function register(): void {
		PhpExecute::init();
		FileRead::init();
		FileWrite::init();
		FileEdit::init();
		FileDelete::init();
		FileList::init();
		WpCliRun::init();
		SiteInfo::init();
		PostQuery::init();
		OptionGet::init();
		OptionSet::init();
		DbQuery::init();
		SkillList::init();
		SkillRead::init();
		SkillCreate::init();
		SkillUpdate::init();
		SkillDelete::init();
	}
}
