<?php
/**
 * Ability: wpcodex/file-read
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Runner\FileManager;

/**
 * Class FileRead
 *
 * @since 1.0.0
 */
class FileRead extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/file-read';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Read File', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Read the contents of a file from the WordPress filesystem. Returns base64-encoded content for binary files.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'   => [
					'type'        => 'string',
					'description' => 'Absolute path or path relative to ABSPATH.',
				],
				'offset' => [
					'type'        => 'integer',
					'description' => 'Byte offset to start reading from. Default 0.',
					'default'     => 0,
					'minimum'     => 0,
				],
				'limit'  => [
					'type'        => 'integer',
					'description' => 'Maximum bytes to read (1–10485760). Default 1048576.',
					'default'     => 1_048_576,
					'minimum'     => 1,
					'maximum'     => 10_485_760,
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
				'path'       => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
				'content'    => [ 'type' => 'string', 'description' => 'File content (UTF-8 text or base64-encoded binary).' ],
				'encoding'   => [ 'type' => 'string', 'enum' => [ 'utf-8', 'base64' ], 'description' => 'Content encoding.' ],
				'size'       => [ 'type' => 'integer', 'description' => 'Total file size in bytes.' ],
				'bytes_read' => [ 'type' => 'integer', 'description' => 'Number of bytes returned in this response.' ],
				'truncated'  => [ 'type' => 'boolean', 'description' => 'True when the file was larger than the requested limit.' ],
				'mime_type'  => [ 'type' => 'string', 'description' => 'Detected MIME type.' ],
			],
			'required' => [ 'path', 'content', 'encoding', 'size', 'bytes_read', 'truncated', 'mime_type' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['path'] ) || ! is_string( $input['path'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
		}

		$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$limit  = max( 1, min( 10_485_760, (int) ( $input['limit'] ?? 1_048_576 ) ) );

		try {
			return FileManager::instance()->read_file( $input['path'], $offset, $limit );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'wpcodex_path_error', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
		}
	}
}
