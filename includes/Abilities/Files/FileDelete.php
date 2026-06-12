<?php
/**
 * Ability: wpcodex/file-delete
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Runner\FileManager;

/**
 * Class FileDelete
 *
 * @since 1.0.0
 */
class FileDelete extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/file-delete';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Delete File', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Delete a file or directory. Idempotent — returns success when the path does not exist. Core WordPress directories are protected.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'      => [
					'type'        => 'string',
					'description' => 'Absolute path or path relative to ABSPATH.',
				],
				'recursive' => [
					'type'        => 'boolean',
					'description' => 'Delete directories and their contents recursively. Required when deleting a directory. Default: false.',
					'default'     => false,
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
				'path'          => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
				'type'          => [ 'type' => 'string', 'description' => 'Entry type: file, dir, or unknown (when path did not exist).' ],
				'deleted'       => [ 'type' => 'boolean', 'description' => 'True when the path was deleted; false when it did not exist.' ],
				'items_deleted' => [ 'type' => 'integer', 'description' => 'Number of filesystem entries deleted.' ],
			],
			'required' => [ 'path', 'type', 'deleted', 'items_deleted' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['path'] ) || ! is_string( $input['path'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
		}

		$recursive = isset( $input['recursive'] ) && true === $input['recursive'];

		try {
			return FileManager::instance()->delete_path( $input['path'], $recursive );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'wpcodex_path_error', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
		}
	}
}
