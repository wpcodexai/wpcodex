<?php
/**
 * Ability: wpcodex/file-disable
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Utils\Helpers;

class FileDisable {

	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/file-disable', [
			'label'       => __( 'Disable Sandbox File', 'wpcodex' ),
			'description' => __(
				'Disables a PHP file in the WPCodex sandbox (wp-content/wpcodex-sandbox/) by appending ".disabled" to its filename. The file is preserved on disk but no longer loaded. Idempotent: disabling an already-disabled file returns disabled=false.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'                 => 'object',
				'properties'           => [
					'path' => [
						'type'        => 'string',
						'description' => 'Absolute path to the sandbox file to disable. Must be inside wp-content/wpcodex-sandbox/.',
						'minLength'   => 1,
					],
				],
				'required'             => [ 'path' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'original_path' => [ 'type' => 'string', 'description' => 'Absolute path of the original file.' ],
					'disabled_path' => [ 'type' => 'string', 'description' => 'Absolute path after renaming (.disabled suffix).' ],
					'disabled'      => [ 'type' => 'boolean', 'description' => 'Whether the file was actually renamed.' ],
				],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$path = isset( $args['path'] ) ? (string) $args['path'] : '';
				if ( '' === $path ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}

				$resolved = realpath( $path );
				if ( false === $resolved ) {
					return new \WP_Error( 'wpcodex_not_found', sprintf( __( 'File not found: %s', 'wpcodex' ), $path ) );
				}

				// Must be inside sandbox directory.
				$sandbox = rtrim( (string) realpath( WPCODEX_SANDBOX_DIR ), '/\\' );
				if ( ! str_starts_with( $resolved, $sandbox . DIRECTORY_SEPARATOR ) && $resolved !== $sandbox ) {
					return new \WP_Error(
						'wpcodex_path_outside_sandbox',
						__( 'Path must be inside the WPCodex sandbox directory.', 'wpcodex' )
					);
				}

				if ( ! is_file( $resolved ) ) {
					return new \WP_Error( 'wpcodex_not_a_file', sprintf( __( 'Not a file: %s', 'wpcodex' ), $resolved ) );
				}

				// Idempotent: already disabled.
				if ( str_ends_with( $resolved, '.disabled' ) ) {
					return [
						'original_path' => $resolved,
						'disabled_path' => $resolved,
						'disabled'      => false,
					];
				}

				$disabled_path = $resolved . '.disabled';

				if ( file_exists( $disabled_path ) ) {
					return new \WP_Error(
						'wpcodex_disabled_exists',
						sprintf( __( 'A disabled version already exists: %s', 'wpcodex' ), $disabled_path )
					);
				}

				if ( ! rename( $resolved, $disabled_path ) ) {
					return new \WP_Error( 'wpcodex_rename_failed', sprintf( __( 'Failed to rename: %s', 'wpcodex' ), $resolved ) );
				}

				return [
					'original_path' => $resolved,
					'disabled_path' => $disabled_path,
					'disabled'      => true,
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
