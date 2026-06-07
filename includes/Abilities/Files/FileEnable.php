<?php
/**
 * Ability: wpcodex/file-enable
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Utils\Helpers;

class FileEnable {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/file-enable', [
			'label'       => __( 'Enable Sandbox File', 'wpcodex' ),
			'description' => __(
				'Re-enables a previously disabled sandbox file by removing the ".disabled" suffix. Accepts either the original filename or the .disabled filename. Only operates inside wp-content/wpcodex-sandbox/. Idempotent: enabling an already-enabled file returns enabled=false.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'                 => 'object',
				'properties'           => [
					'path' => [
						'type'        => 'string',
						'description' => 'Path to the file to re-enable. Can be the original name or the .disabled name. Must be inside wp-content/wpcodex-sandbox/.',
						'minLength'   => 1,
					],
				],
				'required'             => [ 'path' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'disabled_path' => [ 'type' => 'string', 'description' => 'Absolute path of the disabled file.' ],
					'enabled_path'  => [ 'type' => 'string', 'description' => 'Absolute path after renaming (.disabled suffix removed).' ],
					'enabled'       => [ 'type' => 'boolean', 'description' => 'Whether the file was actually renamed.' ],
				],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$path = isset( $args['path'] ) ? (string) $args['path'] : '';
				if ( '' === $path ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}

				$sandbox = rtrim( (string) realpath( WPCODEX_SANDBOX_DIR ), '/\\' );

				// Normalise: look for the .disabled version.
				$disabled_path = str_ends_with( $path, '.disabled' ) ? $path : $path . '.disabled';
				$resolved = realpath( $disabled_path );

				// If .disabled not found, check if original already exists (idempotent).
				if ( false === $resolved ) {
					$original_resolved = realpath( $path );
					if ( false !== $original_resolved && is_file( $original_resolved ) ) {
						return [
							'disabled_path' => $original_resolved,
							'enabled_path'  => $original_resolved,
							'enabled'       => false,
						];
					}
					return new \WP_Error( 'wpcodex_not_found', sprintf( __( 'File not found: %s', 'wpcodex' ), $disabled_path ) );
				}

				// Must be inside sandbox.
				if ( ! str_starts_with( $resolved, $sandbox . DIRECTORY_SEPARATOR ) && $resolved !== $sandbox ) {
					return new \WP_Error(
						'wpcodex_path_outside_sandbox',
						__( 'Path must be inside the WPCodex sandbox directory.', 'wpcodex' )
					);
				}

				if ( ! is_file( $resolved ) ) {
					return new \WP_Error( 'wpcodex_not_a_file', sprintf( __( 'Not a file: %s', 'wpcodex' ), $resolved ) );
				}

				// Remove .disabled suffix.
				$enabled_path = substr( $resolved, 0, -9 ); // strlen('.disabled') = 9

				if ( file_exists( $enabled_path ) ) {
					return new \WP_Error(
						'wpcodex_enabled_exists',
						sprintf( __( 'An enabled version already exists: %s', 'wpcodex' ), $enabled_path )
					);
				}

				if ( ! rename( $resolved, $enabled_path ) ) {
					return new \WP_Error( 'wpcodex_rename_failed', sprintf( __( 'Failed to rename: %s', 'wpcodex' ), $resolved ) );
				}

				return [
					'disabled_path' => $resolved,
					'enabled_path'  => $enabled_path,
					'enabled'       => true,
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
