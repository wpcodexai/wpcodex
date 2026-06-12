<?php
/**
 * Ability: wpcodex/db-query
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Runner\DbRunner;

/**
 * Class DbQuery
 *
 * @since 1.0.0
 */
class DbQuery extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/db-query';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Database Query', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Run a SQL query via $wpdb. Use %s, %d, %f placeholders with the args parameter for safe prepared queries. SELECT returns rows as JSON; INSERT/UPDATE/DELETE return affected row count.', 'wpcodex' ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment,WordPress.WP.I18n.UnorderedPlaceholdersText -- %s/%d/%f are wpdb placeholder types, not i18n format args
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'sql'  => [
					'type'        => 'string',
					'description' => 'SQL query, optionally with $wpdb->prepare() placeholders.',
				],
				'args' => [
					'type'        => 'array',
					'description' => 'Values to bind to placeholders.',
					'items'       => [ 'type' => 'string' ],
				],
			],
			'required'   => [ 'sql' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'JSON array for SELECT queries, or affected row count string.',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string|\WP_Error {
		if ( empty( $input['sql'] ) || ! is_string( $input['sql'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'sql must be a non-empty string.', 'wpcodex' ) );
		}
		$query_args = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : [];
		try {
			return DbRunner::instance()->query( $input['sql'], $query_args );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'wpcodex_db_error', $e->getMessage() );
		}
	}
}
