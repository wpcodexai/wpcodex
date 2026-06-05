<?php
/**
 * Ability: wpcodex/file-read
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;

use WPCodex\Runner\FileManager;
use WPCodex\Utils\Helpers;

class FileRead {

	public static function init(): void {
		wp_register_ability( 'wpcodex/file-read', [
			'label'       => __( 'Read File', 'wpcodex' ),
			'description' => __( 'Read the contents of any file on the server. Path must be absolute.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'path' => [
						'type'        => 'string',
						'description' => 'Absolute server path to the file.',
					],
				],
				'required'   => [ 'path' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'Raw file contents.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}
				try {
					return FileManager::instance()->read( $args['path'] );
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