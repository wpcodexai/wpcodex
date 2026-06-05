<?php
/**
 * Skills Repository — all database operations for skill records.
 *
 * @package WPCodex\Skills
 */

declare( strict_types=1 );

namespace WPCodex\Skills;

/**
 * Class Repository
 */
class Repository {

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
	 * Return all skills (metadata only — no body for list calls).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		$table = Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, name, description, enable_agentic, enable_prompt, created_at, updated_at FROM {$table} ORDER BY name ASC",
			'ARRAY_A'
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_map( static function ( array $row ): array {
			$row['enable_agentic'] = (bool) $row['enable_agentic'];
			$row['enable_prompt']  = (bool) $row['enable_prompt'];
			return $row;
		}, $rows );
	}

	/**
	 * Find a single skill by name.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( string $name ): ?array {
		global $wpdb;
		$table = Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE name = %s LIMIT 1", $name ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'ARRAY_A'
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['enable_agentic'] = (bool) $row['enable_agentic'];
		$row['enable_prompt']  = (bool) $row['enable_prompt'];
		return $row;
	}

	/**
	 * Create a new skill.
	 *
	 * @param array<string, mixed> $data Fields: name, description, body, enable_agentic, enable_prompt.
	 * @return int|\WP_Error Inserted ID on success.
	 */
	public function create( array $data ): int|\WP_Error {
		global $wpdb;

		if ( null !== $this->find( $data['name'] ) ) {
			return new \WP_Error(
				'wpcodex_duplicate',
				/* translators: %s skill name */
				sprintf( __( 'A skill named "%s" already exists.', 'wpcodex' ), esc_html( $data['name'] ) )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			Schema::table_name(),
			[
				'name'           => $data['name'],
				'description'    => $data['description'],
				'body'           => $data['body'],
				'enable_agentic' => $data['enable_agentic'] ? 1 : 0,
				'enable_prompt'  => $data['enable_prompt'] ? 1 : 0,
			],
			[ '%s', '%s', '%s', '%d', '%d' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'wpcodex_db_error', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing skill by name.
	 *
	 * @param string               $name Name/slug of the skill.
	 * @param array<string, mixed> $data Fields to update.
	 * @return true|\WP_Error
	 */
	public function update( string $name, array $data ): true|\WP_Error {
		global $wpdb;

		if ( null === $this->find( $name ) ) {
			return new \WP_Error(
				'wpcodex_not_found',
				/* translators: %s skill name */
				sprintf( __( 'Skill "%s" not found.', 'wpcodex' ), esc_html( $name ) )
			);
		}

		$fields  = [];
		$formats = [];

		if ( isset( $data['description'] ) ) {
			$fields['description'] = $data['description'];
			$formats[]             = '%s';
		}
		if ( isset( $data['body'] ) ) {
			$fields['body'] = $data['body'];
			$formats[]      = '%s';
		}
		if ( isset( $data['enable_agentic'] ) ) {
			$fields['enable_agentic'] = $data['enable_agentic'] ? 1 : 0;
			$formats[]                = '%d';
		}
		if ( isset( $data['enable_prompt'] ) ) {
			$fields['enable_prompt'] = $data['enable_prompt'] ? 1 : 0;
			$formats[]               = '%d';
		}

		if ( empty( $fields ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			Schema::table_name(),
			$fields,
			[ 'name' => $name ],
			$formats,
			[ '%s' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'wpcodex_db_error', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Delete a skill by name.
	 *
	 * @return true|\WP_Error
	 */
	public function delete( string $name ): true|\WP_Error {
		global $wpdb;

		if ( null === $this->find( $name ) ) {
			return new \WP_Error(
				'wpcodex_not_found',
				/* translators: %s skill name */
				sprintf( __( 'Skill "%s" not found.', 'wpcodex' ), esc_html( $name ) )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			Schema::table_name(),
			[ 'name' => $name ],
			[ '%s' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'wpcodex_db_error', $wpdb->last_error );
		}

		return true;
	}
}