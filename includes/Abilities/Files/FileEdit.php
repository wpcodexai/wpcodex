<?php
/**
 * Ability: wpcodex/file-edit
 *
 * Performs a targeted find-and-replace in a file without rewriting the whole
 * file. Safer than a full overwrite for small, precise changes.
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Runner\FileManager;
use WPCodex\Utils\Helpers;

/**
 * Class FileEdit
 */
class FileEdit {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/file-edit', [
			'label'       => __( 'Edit File', 'wpcodex' ),
			'description' => __( 'Replace an exact string in a file. By default requires the string to appear exactly once — set replace_all to true to replace every occurrence.', 'wpcodex' ),
			'category'    => 'wpcodex-general',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'        => [
						'type'        => 'string',
						'description' => 'Absolute path or path relative to ABSPATH.',
					],
					'old_string'  => [
						'type'        => 'string',
						'description' => 'Exact string to find in the file.',
					],
					'new_string'  => [
						'type'        => 'string',
						'description' => 'Replacement string.',
					],
					'replace_all' => [
						'type'        => 'boolean',
						'description' => 'Replace every occurrence of old_string. Default: false (errors if more than one occurrence found).',
						'default'     => false,
					],
				],
				'required'             => [ 'path', 'old_string', 'new_string' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'         => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
					'replacements' => [ 'type' => 'integer', 'description' => 'Number of replacements made.' ],
					'size'         => [ 'type' => 'integer', 'description' => 'File size after edit.' ],
				],
				'required' => [ 'path', 'replacements', 'size' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				foreach ( [ 'path', 'old_string' ] as $key ) {
					if ( ! isset( $args[ $key ] ) || ! is_string( $args[ $key ] ) || '' === $args[ $key ] ) {
						return new \WP_Error(
							'wpcodex_invalid_input',
							/* translators: %s argument name */
							sprintf( __( '%s must be a non-empty string.', 'wpcodex' ), $key )
						);
					}
				}
				if ( ! isset( $args['new_string'] ) || ! is_string( $args['new_string'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'new_string must be a string.', 'wpcodex' ) );
				}

				$replace_all = isset( $args['replace_all'] ) && true === $args['replace_all'];

				try {
					return FileManager::instance()->edit_file(
						$args['path'],
						$args['old_string'],
						$args['new_string'],
						$replace_all
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
