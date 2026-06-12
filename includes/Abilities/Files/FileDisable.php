<?php
/**
 * Ability: wpcodex/file-disable
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Abilities\AbstractAbility;

/**
 * Class FileDisable
 *
 * @since 1.0.0
 */
class FileDisable extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/file-disable';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Disable Sandbox File', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Disables a PHP file in the WPCodex sandbox (wp-content/wpcodex-sandbox/) by appending ".disabled" to its filename. The file is preserved on disk but no longer loaded. Idempotent: disabling an already-disabled file returns disabled=false.',
			'wpcodex'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'original_path' => [ 'type' => 'string', 'description' => 'Absolute path of the original file.' ],
				'disabled_path' => [ 'type' => 'string', 'description' => 'Absolute path after renaming (.disabled suffix).' ],
				'disabled'      => [ 'type' => 'boolean', 'description' => 'Whether the file was actually renamed.' ],
			],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$path = isset( $input['path'] ) ? (string) $input['path'] : '';
		if ( '' === $path ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
		}

		$resolved = realpath( $path );
		if ( false === $resolved ) {
			/* translators: %s: file path */
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
			/* translators: %s: file path */
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
				/* translators: %s: file path */
				sprintf( __( 'A disabled version already exists: %s', 'wpcodex' ), $disabled_path )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- WP_Filesystem::move() requires filesystem init not available in this context
		if ( ! rename( $resolved, $disabled_path ) ) {
			/* translators: %s: file path */
			return new \WP_Error( 'wpcodex_rename_failed', sprintf( __( 'Failed to rename: %s', 'wpcodex' ), $resolved ) );
		}

		return [
			'original_path' => $resolved,
			'disabled_path' => $disabled_path,
			'disabled'      => true,
		];
	}
}
