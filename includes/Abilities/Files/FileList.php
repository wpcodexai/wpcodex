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

/**
 * Class FileList
 */
class FileList {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/file-list', [
			'label'       => __( 'List Directory', 'wpcodex' ),
			'description' => __( 'List the contents of a directory. Supports glob patterns, depth control, and hidden-file filtering.', 'wpcodex' ),
			'category'    => 'wpcodex-general',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'           => [
						'type'        => 'string',
						'description' => 'Absolute path or path relative to ABSPATH.',
					],
					'pattern'        => [
						'type'        => 'string',
						'description' => 'Glob pattern to filter entries (e.g. "*.php"). Default: * (all entries).',
						'default'     => '*',
					],
					'max_depth'      => [
						'type'        => 'integer',
						'description' => 'Maximum directory depth to traverse (1–10). Default: 3.',
						'default'     => 3,
						'minimum'     => 1,
						'maximum'     => 10,
					],
					'include_hidden' => [
						'type'        => 'boolean',
						'description' => 'Include entries whose names start with a dot. Default: false.',
						'default'     => false,
					],
					'limit'          => [
						'type'        => 'integer',
						'description' => 'Maximum number of entries to return (1–5000). Default: 500.',
						'default'     => 500,
						'minimum'     => 1,
						'maximum'     => 5000,
					],
				],
				'required'             => [ 'path' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'path'      => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
					'entries'   => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'name'        => [ 'type' => 'string' ],
								'path'        => [ 'type' => 'string' ],
								'type'        => [ 'type' => 'string', 'enum' => [ 'file', 'dir' ] ],
								'size'        => [ 'type' => [ 'integer', 'null' ] ],
								'permissions' => [ 'type' => 'string' ],
								'modified'    => [ 'type' => 'integer' ],
							],
						],
					],
					'total'     => [ 'type' => 'integer', 'description' => 'Total matching entries found (before limit).' ],
					'truncated' => [ 'type' => 'boolean', 'description' => 'True when total exceeds the limit.' ],
				],
				'required' => [ 'path', 'entries', 'total', 'truncated' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				if ( empty( $args['path'] ) || ! is_string( $args['path'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
				}

				$pattern        = is_string( $args['pattern'] ?? null ) ? (string) $args['pattern'] : '*';
				$max_depth      = max( 1, min( 10, (int) ( $args['max_depth'] ?? 3 ) ) );
				$include_hidden = isset( $args['include_hidden'] ) && true === $args['include_hidden'];
				$limit          = max( 1, min( 5000, (int) ( $args['limit'] ?? 500 ) ) );

				try {
					return FileManager::instance()->list_directory(
						$args['path'],
						$pattern,
						$max_depth,
						$include_hidden,
						$limit
					);
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
