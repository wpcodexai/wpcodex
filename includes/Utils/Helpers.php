<?php
/**
 * Shared helpers used across ability files.
 *
 * @package WPCodex\Utils
 */

declare( strict_types=1 );

namespace WPCodex\Utils;
/**
 * Class Helpers
 */
class Helpers {

	/**
	 * Standard permission callback — requires manage_options capability.
	 *
	 * Every ability passes this as its permission_callback so the check is
	 * consistent and testable in one place.
	 */
	public static function ability_permission(): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'wpcodex_not_authenticated',
				__( 'You must be logged in to use WPCodex abilities.', 'wpcodex' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'wpcodex_insufficient_capability',
				__( 'You must have the manage_options capability to use WPCodex abilities.', 'wpcodex' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}
}