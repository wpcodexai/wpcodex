<?php
/**
 * Ability: wpcodex/file-edit
 *
 * Performs a targeted find-and-replace in a file without rewriting the whole
 * file. Safer than a full overwrite for small, precise changes.
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Runner\FileManager;

/**
 * Class FileEdit
 *
 * @since 1.0.0
 */
class FileEdit extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/file-edit';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Edit File', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Replace an exact string in a file. By default requires the string to appear exactly once — set replace_all to true to replace every occurrence.', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
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
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'         => [ 'type' => 'string', 'description' => 'Resolved absolute path.' ],
				'replacements' => [ 'type' => 'integer', 'description' => 'Number of replacements made.' ],
				'size'         => [ 'type' => 'integer', 'description' => 'File size after edit.' ],
			],
			'required' => [ 'path', 'replacements', 'size' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		foreach ( [ 'path', 'old_string' ] as $key ) {
			if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) || '' === $input[ $key ] ) {
				return new \WP_Error(
					'wpcodex_invalid_input',
					/* translators: %s argument name */
					sprintf( __( '%s must be a non-empty string.', 'wpcodex' ), $key )
				);
			}
		}
		if ( ! isset( $input['new_string'] ) || ! is_string( $input['new_string'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'new_string must be a string.', 'wpcodex' ) );
		}

		$replace_all = isset( $input['replace_all'] ) && true === $input['replace_all'];

		try {
			return FileManager::instance()->edit_file(
				$input['path'],
				$input['old_string'],
				$input['new_string'],
				$replace_all
			);
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'wpcodex_path_error', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'wpcodex_file_error', $e->getMessage() );
		}
	}
}
