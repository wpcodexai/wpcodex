<?php
/**
 * Skills admin notices — surfaces an MCP-reload reminder after skill changes.
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Skills;

/**
 * Class Notices
 *
 * Stores a transient flag when a skill is created, updated, or deleted.
 * On the next admin page load the flag is consumed and a notice is shown
 * reminding the user (or agent) that MCP clients may need to re-discover
 * abilities to pick up the changed skill catalog.
 */
class Notices {

	private const TRANSIENT_KEY = 'allyworker_transient_skill_reload_notice';

	/**
	 * Constructor — no hook needed; notice is consumed by SkillsPage directly.
	 */
	public function __construct() {
		// Intentionally empty. The notice is pulled by SkillsPage::pending_reload_notice()
		// rather than pushed via admin_notices, so it flows through the same render_notices()
		// path and respects the wp-header-end anchor.
	}

	/**
	 * Set the pending reload notice transient. Call after any skill mutation.
	 */
	public static function set_pending_reload_notice(): void {
		set_transient( self::TRANSIENT_KEY, '1', 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Consume the transient and return a notice array if one is pending, or null.
	 *
	 * @return array{type: string, message: string}|null
	 */
	public static function pending_reload_notice(): ?array {
		if ( ! get_transient( self::TRANSIENT_KEY ) ) {
			return null;
		}

		delete_transient( self::TRANSIENT_KEY );

		return [
			'type'    => 'info',
			'message' => __( 'Skills updated. MCP clients should re-discover abilities to reflect the latest skill catalog.', 'allyworker' ),
		];
	}
}
