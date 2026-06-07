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

class FileDelete {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }

	public function init(): void {
		wp_register_ability( 'wpcodex/file-delete', [
			'label'       => __( 'Delete File', 'wpcodex' ),
			'description' => __( 'Delete a file from the server. This is irreversible — no backup is created.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'path' => [
						'type'        => 'string',
						'description' => 'Absolute server path to the file to delete.',
					],
				],
				'required'   => [ 'path' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'Success message.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}
				try {
					return FileManager::instance()->delete( $args['path'] );
				} catch ( \Throwable $e ) {
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
