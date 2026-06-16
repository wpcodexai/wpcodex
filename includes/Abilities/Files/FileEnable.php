<?php
/**
 * Ability: wpworker/file-enable
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Files;

use WPWorker\Abilities\AbstractAbility;

/**
 * Class FileEnable
 *
 * @since 1.0.0
 */
class FileEnable extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/file-enable';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Enable Sandbox File', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Re-enables a previously disabled sandbox file by removing the ".disabled" suffix. Accepts either the original filename or the .disabled filename. Only operates inside wp-content/wpworker-sandbox/. Idempotent: enabling an already-enabled file returns enabled=false.',
			'worker-ai'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'path' => [
					'type'        => 'string',
					'description' => 'Path to the file to re-enable. Can be the original name or the .disabled name. Must be inside wp-content/wpworker-sandbox/.',
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
				'disabled_path' => [ 'type' => 'string', 'description' => 'Absolute path of the disabled file.' ],
				'enabled_path'  => [ 'type' => 'string', 'description' => 'Absolute path after renaming (.disabled suffix removed).' ],
				'enabled'       => [ 'type' => 'boolean', 'description' => 'Whether the file was actually renamed.' ],
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
			return new \WP_Error( 'wpworker_invalid_input', __( 'path must be a non-empty string.', 'worker-ai' ) );
		}

		$sandbox = rtrim( (string) realpath( WPWORKER_SANDBOX_DIR ), '/\\' );

		// Normalise: look for the .disabled version.
		$disabled_path = str_ends_with( $path, '.disabled' ) ? $path : $path . '.disabled';
		$resolved      = realpath( $disabled_path );

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
			/* translators: %s: file path */
			return new \WP_Error( 'wpworker_not_found', sprintf( __( 'File not found: %s', 'worker-ai' ), $disabled_path ) );
		}

		// Must be inside sandbox.
		if ( ! str_starts_with( $resolved, $sandbox . DIRECTORY_SEPARATOR ) && $resolved !== $sandbox ) {
			return new \WP_Error(
				'wpworker_path_outside_sandbox',
				__( 'Path must be inside the WPWorker sandbox directory.', 'worker-ai' )
			);
		}

		if ( ! is_file( $resolved ) ) {
			/* translators: %s: file path */
			return new \WP_Error( 'wpworker_not_a_file', sprintf( __( 'Not a file: %s', 'worker-ai' ), $resolved ) );
		}

		// Remove .disabled suffix.
		$enabled_path = substr( $resolved, 0, -9 ); // strlen('.disabled') = 9

		if ( file_exists( $enabled_path ) ) {
			return new \WP_Error(
				'wpworker_enabled_exists',
				/* translators: %s: file path */
				sprintf( __( 'An enabled version already exists: %s', 'worker-ai' ), $enabled_path )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- WP_Filesystem::move() requires filesystem init not available in this context
		if ( ! rename( $resolved, $enabled_path ) ) {
			/* translators: %s: file path */
			return new \WP_Error( 'wpworker_rename_failed', sprintf( __( 'Failed to rename: %s', 'worker-ai' ), $resolved ) );
		}

		return [
			'disabled_path' => $resolved,
			'enabled_path'  => $enabled_path,
			'enabled'       => true,
		];
	}
}
