<?php
/**
 * Ability: wpcodex/file-edit
 *
 * Performs a targeted find-and-replace in a file without rewriting the whole
 * content — matches Novamira's edit-file ability.
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;

use WPCodex\Runner\FileManager;
use WPCodex\Utils\Helpers;

class FileEdit {

	public static function init(): void {
		wp_register_ability( 'wpcodex/file-edit', [
			'label'       => __( 'Edit File', 'wpcodex' ),
			'description' => __(
				'Make a precise targeted edit to a file using exact string replacement. Provide the exact string to find and the replacement. A .bak backup is created before editing.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'        => [
						'type'        => 'string',
						'description' => 'Absolute server path to the file.',
					],
					'search'      => [
						'type'        => 'string',
						'description' => 'Exact string to search for. Must appear exactly once in the file.',
					],
					'replacement' => [
						'type'        => 'string',
						'description' => 'String to replace the found occurrence with.',
					],
				],
				'required'   => [ 'path', 'search', 'replacement' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'Success message.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				foreach ( [ 'path', 'search', 'replacement' ] as $key ) {
					if ( ! isset( $args[ $key ] ) || ! is_string( $args[ $key ] ) ) {
						return new \WP_Error(
							'wpcodex_invalid_input',
							/* translators: %s argument name */
							sprintf( __( '%s must be a string.', 'wpcodex' ), $key )
						);
					}
				}

				if ( '' === $args['search'] ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'search must not be empty.', 'wpcodex' ) );
				}

				try {
					return FileManager::instance()->edit( $args['path'], $args['search'], $args['replacement'] );
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
