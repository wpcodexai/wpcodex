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

class FileWrite {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/file-write', [
			'label'       => __( 'Write File', 'wpcodex' ),
			'description' => __( 'Write the contents of any file on the server. Path must be absolute.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'path' => [
						'type'        => 'string',
						'description' => 'Absolute server path to the file.',
					],
					'content' => [
						'type'        => 'string',
						'description' => 'Full content to write to the file.',
					],
				],
				'required'   => [ 'path', 'content' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'Success message.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}
				if ( empty( $args['content'] ) || ! is_string( $args['content'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'content must be a non-empty string.', 'wpcodex' ) );
				}
				try {
					return FileManager::instance()->write( $args['path'], $args['content'] );
				} catch ( \Throwable $e ) {
					return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
				}
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
