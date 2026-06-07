<?php
/**
 * Ability registration aggregator.
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;
use WPCodex\Abilities\Files;
use WPCodex\Abilities\Skills;
use WPCodex\Utils\Helpers;
use WPCodex\Abilities\Core;
use WPCodex\Abilities\Site;

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
		new Core\DiscoverAbilities();
		new Files\FileRead();
		new Files\FileWrite();
		new Files\FileEdit();
		new Files\FileDelete();
		new Files\FileList();
		new Files\FileDisable();
		new Files\FileEnable();
		new Site\WpCliRun();
		new Site\SiteInfo();
		new Site\PostQuery();
		new Site\OptionGet();
		new Site\OptionSet();
		new Site\DbQuery();
		new Site\PhpExecute();
		new Skills\SkillList();
		new Skills\SkillRead();
		new Skills\SkillCreate();
		new Skills\SkillUpdate();
		new Skills\SkillDelete();

		/**
         * Fire wpcodex/register_abilities so every ability class
         * runs its init() method at exactly the right moment —
         * inside wp_abilities_api_init, after all classes are instantiated.
         */
        do_action( 'wpcodex/register_abilities' );
	}
}
