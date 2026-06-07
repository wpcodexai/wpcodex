<?php
/**
 * Ability: wpcodex/file-read
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Runner\FileManager;
use WPCodex\Utils\Helpers;

/**
 * Class FileRead
 */
class FileRead {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/file-read', [
			'label'       => __( 'Read File', 'wpcodex' ),
			'description' => __( 'Read the contents of a file from the WordPress filesystem. Returns base64-encoded content for binary files.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
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
			],

			'output_schema' => [
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
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}

				$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
				$limit  = max( 1, min( 10_485_760, (int) ( $args['limit'] ?? 1_048_576 ) ) );

				try {
					return FileManager::instance()->read_file( $args['path'], $offset, $limit );
				} catch ( \InvalidArgumentException $e ) {
					return new \WP_Error( 'wpcodex_path_error', $e->getMessage() );
				} catch ( \RuntimeException $e ) {
					return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
				}
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
