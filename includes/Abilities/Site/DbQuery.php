<?php
/**
 * Ability: wpcodex/db-query
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Runner\DbRunner;
use WPCodex\Utils\Helpers;

class DbQuery {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/db-query', [
			'label'       => __( 'Database Query', 'wpcodex' ),
			'description' => __(
				'Run a SQL query via $wpdb. Use %s, %d, %f placeholders with the args parameter for safe prepared queries. SELECT returns rows as JSON; INSERT/UPDATE/DELETE return affected row count.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
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
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'JSON array for SELECT queries, or affected row count string.',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( empty( $args['sql'] ) || ! is_string( $args['sql'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'sql must be a non-empty string.', 'wpcodex' ) );
				}
				$query_args = isset( $args['args'] ) && is_array( $args['args'] ) ? $args['args'] : [];
				try {
					return DbRunner::instance()->query( $args['sql'], $query_args );
				} catch ( \Throwable $e ) {
					return new \WP_Error( 'wpcodex_db_error', $e->getMessage() );
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
