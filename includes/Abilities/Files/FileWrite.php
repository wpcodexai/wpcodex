<?php
/**
 * Ability: wpcodex/file-write
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Runner\FileManager;
use WPCodex\Utils\Helpers;

/**
 * Class FileWrite
 */
class FileWrite {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/file-write', [
			'label'       => __( 'Write File', 'wpcodex' ),
			'description' => __( 'Write content to a file. Creates the file if it does not exist. PHP files are restricted to the sandbox directory.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
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
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'                => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
					'bytes_written'       => [ 'type' => 'integer', 'description' => 'Bytes written.' ],
					'created'             => [ 'type' => 'boolean', 'description' => 'True when the file was newly created.' ],
					'directories_created' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Directories created during the write.' ],
					'size'                => [ 'type' => 'integer', 'description' => 'File size after write.' ],
				],
				'required' => [ 'path', 'bytes_written', 'created', 'directories_created', 'size' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}
				if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'content must be a string.', 'wpcodex' ) );
				}

				$encoding           = in_array( $args['encoding'] ?? 'utf-8', [ 'utf-8', 'base64' ], true )
					? (string) ( $args['encoding'] ?? 'utf-8' )
					: 'utf-8';
				$mode               = in_array( $args['mode'] ?? 'overwrite', [ 'overwrite', 'append' ], true )
					? (string) ( $args['mode'] ?? 'overwrite' )
					: 'overwrite';
				$create_directories = ! array_key_exists( 'create_directories', $args ) || true === $args['create_directories'];

				try {
					return FileManager::instance()->write_file(
						$args['path'],
						$args['content'],
						$encoding,
						$mode,
						$create_directories
					);
				} catch ( \InvalidArgumentException $e ) {
					return new \WP_Error( 'wpcodex_path_error', $e->getMessage() );
				} catch ( \RuntimeException $e ) {
					return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
				}
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
