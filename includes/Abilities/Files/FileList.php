<?php
/**
 * Ability: wpworker/file-list
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Files;

use WPWorker\Abilities\AbstractAbility;
use WPWorker\Runner\FileManager;

/**
 * Class FileList
 *
 * @since 1.0.0
 */
class FileList extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/file-list';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'List Directory', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'List the contents of a directory. Supports glob patterns, depth control, and hidden-file filtering.', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['path'] ) || ! is_string( $input['path'] ) ) {
			return new \WP_Error( 'wpworker_invalid_input', __( 'path must be a non-empty string.', 'worker-ai' ) );
		}

		$pattern        = is_string( $input['pattern'] ?? null ) ? (string) $input['pattern'] : '*';
		$max_depth      = max( 1, min( 10, (int) ( $input['max_depth'] ?? 3 ) ) );
		$include_hidden = isset( $input['include_hidden'] ) && true === $input['include_hidden'];
		$limit          = max( 1, min( 5000, (int) ( $input['limit'] ?? 500 ) ) );

		try {
			return FileManager::instance()->list_directory(
				$input['path'],
				$pattern,
				$max_depth,
				$include_hidden,
				$limit
			);
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'wpworker_path_error', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'wpworker_file_error', $e->getMessage() );
		}
	}
}
