<?php
/**
 * Ability: wpcodex/file-write
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Runner\FileManager;

/**
 * Class FileWrite
 *
 * @since 1.0.0
 */
class FileWrite extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/file-write';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Write File', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Write content to a file. Creates the file if it does not exist. PHP files are restricted to the sandbox directory.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'                => [
					'type'        => 'string',
					'description' => 'Absolute path or path relative to ABSPATH.',
				],
				'content'             => [
					'type'        => 'string',
					'description' => 'File content. When encoding is "base64", provide base64-encoded binary.',
				],
				'encoding'            => [
					'type'        => 'string',
					'enum'        => [ 'utf-8', 'base64' ],
					'description' => 'Content encoding. Default: utf-8.',
					'default'     => 'utf-8',
				],
				'mode'                => [
					'type'        => 'string',
					'enum'        => [ 'overwrite', 'append' ],
					'description' => 'Write mode. "overwrite" replaces the file (atomic, .bak backup created). "append" adds to the end. Default: overwrite.',
					'default'     => 'overwrite',
				],
				'create_directories'  => [
					'type'        => 'boolean',
					'description' => 'Create missing parent directories. Default: true.',
					'default'     => true,
				],
			],
			'required'             => [ 'path', 'content' ],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'                => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
				'bytes_written'       => [ 'type' => 'integer', 'description' => 'Bytes written.' ],
				'created'             => [ 'type' => 'boolean', 'description' => 'True when the file was newly created.' ],
				'directories_created' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Directories created during the write.' ],
				'size'                => [ 'type' => 'integer', 'description' => 'File size after write.' ],
			],
			'required' => [ 'path', 'bytes_written', 'created', 'directories_created', 'size' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['path'] ) || ! is_string( $input['path'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
		}
		if ( ! isset( $input['content'] ) || ! is_string( $input['content'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'content must be a string.', 'wpcodex' ) );
		}

		$encoding           = in_array( $input['encoding'] ?? 'utf-8', [ 'utf-8', 'base64' ], true )
			? (string) ( $input['encoding'] ?? 'utf-8' )
			: 'utf-8';
		$mode               = in_array( $input['mode'] ?? 'overwrite', [ 'overwrite', 'append' ], true )
			? (string) ( $input['mode'] ?? 'overwrite' )
			: 'overwrite';
		$create_directories = ! array_key_exists( 'create_directories', $input ) || true === $input['create_directories'];

		try {
			return FileManager::instance()->write_file(
				$input['path'],
				$input['content'],
				$encoding,
				$mode,
				$create_directories
			);
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'wpcodex_path_error', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
		}
	}
}
