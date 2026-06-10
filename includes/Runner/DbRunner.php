<?php
/**
 * DB Runner — $wpdb query interface.
 *
 * @package WPCodex\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Runner;

/**
 * Class DbRunner
 */
class DbRunner {

	/** @var self|null */
	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Execute a SQL query via $wpdb.
	 *
	 * @param string  $sql  SQL with optional $wpdb->prepare() placeholders.
	 * @param mixed[] $args Values for placeholders.
	 * @return string JSON rows for SELECT; affected count for mutations.
	 *
	 * @throws \RuntimeException On query failure.
	 */
	public function query( string $sql, array $args = [] ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = ! empty( $args ) ? $wpdb->prepare( $sql, ...$args ) : $sql;

		$verb = strtoupper( strtok( ltrim( $prepared ), " \t\n\r" ) ?: '' );

		if ( in_array( $verb, [ 'SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN' ], true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results( $prepared, ARRAY_A );
			if ( '' !== (string) $wpdb->last_error ) {
				throw new \RuntimeException( '[WPCodex DB] ' . esc_html( $wpdb->last_error ) );
			}
			return wp_json_encode( $results, JSON_PRETTY_PRINT ) ?: '[]';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$affected = $wpdb->query( $prepared );
		if ( false === $affected ) {
			throw new \RuntimeException( '[WPCodex DB] ' . esc_html( $wpdb->last_error ) );
		}
		return sprintf( 'Query OK. Rows affected: %d', (int) $affected );
	}
}