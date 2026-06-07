<?php
/**
 * Ability: wpcodex/file-list
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Runner\FileManager;
use WPCodex\Utils\Helpers;

class FileList {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/file-list', [
			'label'       => __( 'List Directory', 'wpcodex' ),
			'description' => __(
				'List files and directories in a given path. Returns a JSON array of file info objects.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'      => [
						'type'        => 'string',
						'description' => 'Absolute server path to the directory.',
					],
					'recursive' => [
						'type'        => 'boolean',
						'description' => 'Whether to list files recursively. Default: false.',
						'default'     => false,
					],
				],
				'required'   => [ 'path' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'JSON-encoded array of {name, path, type, size, modified} objects.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}
				$recursive = isset( $args['recursive'] ) ? (bool) $args['recursive'] : false;
				try {
					return FileManager::instance()->list( $args['path'], $recursive );
				} catch ( \Throwable $e ) {
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
