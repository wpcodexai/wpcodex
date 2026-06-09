<?php
/**
 * Ability registration aggregator.
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;

use WPCodex\Abilities\Core;
use WPCodex\Abilities\Files;
use WPCodex\Abilities\Gutenberg;
use WPCodex\Abilities\Site;
use WPCodex\Abilities\Skills;

class Abilities {

	public function __construct() {
		add_action( 'wp_abilities_api_init', [ $this, 'register' ] );
	}

	/**
	 * Register all abilities. Each ability is responsible for its own registration
	 * via the wpcodex/register_abilities action hook.
	 * New abilities should be added here.
	 */
	public function register(): void {
		new Core\DiscoverAbilities();

		// File abilities.
		new Files\FileRead();
		new Files\FileWrite();
		new Files\FileEdit();
		new Files\FileDelete();
		new Files\FileList();
		new Files\FileDisable();
		new Files\FileEnable();
		new Files\CreateUploadLink();

		// Site / WordPress abilities.
		new Site\WpCliRun();
		new Site\SiteInfo();
		new Site\PostQuery();
		new Site\OptionGet();
		new Site\OptionSet();
		new Site\DbQuery();
		new Site\PhpExecute();
		new Site\CreateAdminAccessLink();

		// Skills abilities.
		new Skills\SkillList();
		new Skills\SkillRead();
		new Skills\SkillCreate();
		new Skills\SkillUpdate();
		new Skills\SkillDelete();
		new Skills\SkillListRevisions();
		new Skills\SkillRestoreRevision();

		// Gutenberg / Block Editor Queue abilities.
		new Gutenberg\GetContent();
		new Gutenberg\WriteContent();
		new Gutenberg\GetFinalizationUrl();
		new Gutenberg\CreatePadding();
		new Gutenberg\AddPaddingChange();
		new Gutenberg\EnableFinalization();
		new Gutenberg\DeletePadding();
		new Gutenberg\DeletePaddingChange();
		new Gutenberg\GetPadding();
		new Gutenberg\ListPadding();
		new Gutenberg\GetFinalizerRuntime();

		/**
		 * Fire wpcodex/register_abilities so every ability class
		 * runs its init() method at exactly the right moment —
		 * inside wp_abilities_api_init, after all classes are instantiated.
		 */
		do_action( 'wpcodex/register_abilities' );
	}
}
