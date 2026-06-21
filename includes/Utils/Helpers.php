<?php
/**
 * Shared helpers used across ability files.
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Utils;
/**
 * Class Helpers
 */
class Helpers {

	/**
	 * Standard permission callback — requires manage_options capability.
	 *
	 * On multisite, super-admin status is required instead of per-site
	 * manage_options, matching the elevated privilege model WordPress uses
	 * for network-wide administrative actions.
	 *
	 * Every ability passes this as its permission_callback so the check is
	 * consistent and testable in one place.
	 */
	public static function ability_permission(): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'allyworker_not_authenticated',
				__( 'You must be logged in to use AllyWorker abilities.', 'allyworker' ),
				[ 'status' => 401 ]
			);
		}

		$allowed = is_multisite() ? is_super_admin() : current_user_can( 'manage_options' );

		if ( ! $allowed ) {
			return new \WP_Error(
				'allyworker_insufficient_capability',
				__( 'You must have the manage_options capability (or be a super admin on multisite) to use AllyWorker abilities.', 'allyworker' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}
}