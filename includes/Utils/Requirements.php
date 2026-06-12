<?php
/**
 * Requirements checker.
 *
 * @package WPCodex
 */

declare( strict_types=1 );

namespace WPCodex\Utils;

/**
 * Class Requirements
 */
class Requirements {

	private const MIN_PHP = '8.0';
	private const MIN_WP  = '6.9';

	/**
	 * Check if the environment meets the plugin's requirements.
	 *
	 * If not, display admin notices with the errors.
	 *
	 * @return bool True if requirements are met, false otherwise.
	 */
	public static function check(): bool {
		$errors = [];

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required version 2: current version */
				__( 'WPCodex requires PHP %1$s or higher. You are running %2$s.', 'wpcodex' ),
				self::MIN_PHP,
				PHP_VERSION
			);
		}

		if ( version_compare( get_bloginfo( 'version' ), self::MIN_WP, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required version 2: current version */
				__( 'WPCodex requires WordPress %1$s or higher (Abilities API). You are running %2$s.', 'wpcodex' ),
				self::MIN_WP,
				get_bloginfo( 'version' )
			);
		}

		if ( ! empty( $errors ) ) {
			add_action( 'admin_notices', static function () use ( $errors ): void {
				foreach ( $errors as $error ) {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html( $error )
					);
				}
			} );
			return false;
		}

		return true;
	}
}