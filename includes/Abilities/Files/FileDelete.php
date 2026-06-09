<?php
/**
 * Ability: wpcodex/file-delete
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Runner\FileManager;
use WPCodex\Utils\Helpers;

/**
 * Class FileDelete
 */
class FileDelete {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/file-delete', [
			'label'       => __( 'Delete File', 'wpcodex' ),
			'description' => __( 'Delete a file or directory. Idempotent — returns success when the path does not exist. Core WordPress directories are protected.', 'wpcodex' ),
			'category'    => 'wpcodex-general',

			'input_schema' => [
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
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'          => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
					'type'          => [ 'type' => 'string', 'description' => 'Entry type: file, dir, or unknown (when path did not exist).' ],
					'deleted'       => [ 'type' => 'boolean', 'description' => 'True when the path was deleted; false when it did not exist.' ],
					'items_deleted' => [ 'type' => 'integer', 'description' => 'Number of filesystem entries deleted.' ],
				],
				'required' => [ 'path', 'type', 'deleted', 'items_deleted' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}

				$recursive = isset( $args['recursive'] ) && true === $args['recursive'];

				try {
					return FileManager::instance()->delete_path( $args['path'], $recursive );
				} catch ( \InvalidArgumentException $e ) {
					return new \WP_Error( 'wpcodex_path_error', $e->getMessage() );
				} catch ( \RuntimeException $e ) {
					return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
				}
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
