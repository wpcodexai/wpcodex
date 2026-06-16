<?php
/**
 * Skills Repository — all database operations for skill records.
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Skills;

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, name, description, enable_agentic, enable_prompt, created_at, updated_at FROM {$table} ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
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
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['enable_agentic'] = (bool) $row['enable_agentic'];
		$row['enable_prompt']  = (bool) $row['enable_prompt'];
		return $row;
	}

	/**
	 * Create a new skill, with optional conflict resolution.
	 *
	 * @param array<string, mixed> $data        Fields: name, description, body, enable_agentic, enable_prompt.
	 * @param string               $on_conflict 'fail' (default), 'replace', or 'rename'.
	 * @return array{id: int, name: string, action: string}|\WP_Error
	 */
	public function create( array $data, string $on_conflict = 'fail' ): array|\WP_Error {
		global $wpdb;

		$name     = (string) $data['name'];
		$existing = $this->find( $name );
		$action   = 'created';

		if ( null !== $existing ) {
			if ( 'fail' === $on_conflict ) {
				return new \WP_Error(
					'wpworker_duplicate',
					/* translators: %s skill name */
					sprintf( __( 'A skill named "%s" already exists.', 'worker-ai' ), esc_html( $name ) )
				);
			}

			if ( 'replace' === $on_conflict ) {
				// Snapshot before replacing so the old version is recoverable.
				$this->snapshot_revision( $name );
				$del = $this->delete( $name );
				if ( is_wp_error( $del ) ) {
					return $del;
				}
				$action = 'replaced';
			} elseif ( 'rename' === $on_conflict ) {
				$name         = $this->find_free_name( $name );
				$data['name'] = $name;
				$action       = 'renamed';
			}
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
			return new \WP_Error( 'wpworker_db_error', $wpdb->last_error );
		}

		Notices::set_pending_reload_notice();

		return [
			'id'     => (int) $wpdb->insert_id,
			'name'   => $name,
			'action' => $action,
		];
	}

	/**
	 * Find a name that does not already exist by appending -2, -3, etc.
	 */
	private function find_free_name( string $base ): string {
		$i = 2;
		while ( null !== $this->find( $base . '-' . $i ) ) {
			++$i;
			if ( $i > 9999 ) {
				return $base . '-' . time();
			}
		}
		return $base . '-' . $i;
	}

	/**
	 * Update an existing skill by name.
	 *
	 * @param string               $name Name/slug of the skill.
	 * @param array<string, mixed> $data Fields to update.
	 * @return array{name: string, changed_fields: list<string>}|\WP_Error
	 */
	public function update( string $name, array $data ): array|\WP_Error {
		global $wpdb;

		if ( null === $this->find( $name ) ) {
			return new \WP_Error(
				'wpworker_not_found',
				/* translators: %s skill name */
				sprintf( __( 'Skill "%s" not found.', 'worker-ai' ), esc_html( $name ) )
			);
		}

		$fields         = [];
		$formats        = [];
		$changed_fields = [];

		if ( isset( $data['description'] ) ) {
			$fields['description'] = $data['description'];
			$formats[]             = '%s';
			$changed_fields[]      = 'description';
		}
		if ( isset( $data['body'] ) ) {
			$fields['body'] = $data['body'];
			$formats[]      = '%s';
			$changed_fields[] = 'body';
		}
		if ( isset( $data['enable_agentic'] ) ) {
			$fields['enable_agentic'] = $data['enable_agentic'] ? 1 : 0;
			$formats[]                = '%d';
			$changed_fields[]         = 'enable_agentic';
		}
		if ( isset( $data['enable_prompt'] ) ) {
			$fields['enable_prompt'] = $data['enable_prompt'] ? 1 : 0;
			$formats[]               = '%d';
			$changed_fields[]        = 'enable_prompt';
		}

		if ( empty( $fields ) ) {
			return [ 'name' => $name, 'changed_fields' => [] ];
		}

		// Snapshot before overwriting body or description.
		if ( isset( $data['body'] ) || isset( $data['description'] ) ) {
			$this->snapshot_revision( $name );
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
			return new \WP_Error( 'wpworker_db_error', $wpdb->last_error );
		}

		Notices::set_pending_reload_notice();

		return [ 'name' => $name, 'changed_fields' => $changed_fields ];
	}

	/**
	 * Delete a skill by name. Idempotent — not-found is treated as success.
	 *
	 * @return bool|\WP_Error
	 */
	public function delete( string $name ): bool|\WP_Error {
		global $wpdb;

		if ( null === $this->find( $name ) ) {
			return true; // idempotent: already absent
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			Schema::table_name(),
			[ 'name' => $name ],
			[ '%s' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'wpworker_db_error', $wpdb->last_error );
		}

		Notices::set_pending_reload_notice();

		return true;
	}

	// -------------------------------------------------------------------------
	// Revision history
	// -------------------------------------------------------------------------

	/**
	 * Return up to 10 revisions for a skill, newest first.
	 *
	 * Each row: { id, skill_name, body, description, enable_agentic, enable_prompt, created_at }
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_revisions( string $name ): array {
		global $wpdb;
		$table = Schema::revisions_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE skill_name = %s ORDER BY id DESC LIMIT 10", $name ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
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
	 * Restore a skill to the state captured in the given revision.
	 *
	 * The current state is snapshotted first so the restore itself is reversible.
	 *
	 * @return bool|\WP_Error
	 */
	public function restore_revision( int $revision_id ): bool|\WP_Error {
		global $wpdb;

		$table = Schema::revisions_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rev = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $revision_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $rev ) ) {
			return new \WP_Error(
				'wpworker_not_found',
				/* translators: %d revision ID */
				sprintf( __( 'Revision #%d not found.', 'worker-ai' ), $revision_id )
			);
		}

		$skill_name = (string) $rev['skill_name'];

		if ( null === $this->find( $skill_name ) ) {
			return new \WP_Error(
				'wpworker_not_found',
				/* translators: %s skill name */
				sprintf( __( 'Skill "%s" not found.', 'worker-ai' ), esc_html( $skill_name ) )
			);
		}

		// Snapshot current state so the restore is itself reversible.
		$this->snapshot_revision( $skill_name );

		// Apply the revision.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			Schema::table_name(),
			[
				'body'           => (string) $rev['body'],
				'description'    => (string) $rev['description'],
				'enable_agentic' => (int) $rev['enable_agentic'],
				'enable_prompt'  => (int) $rev['enable_prompt'],
			],
			[ 'name' => $skill_name ],
			[ '%s', '%s', '%d', '%d' ],
			[ '%s' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'wpworker_db_error', $wpdb->last_error );
		}

		$this->prune_revisions( $skill_name );
		return true;
	}

	/**
	 * Snapshot the current state of a skill into the revisions table.
	 * Does nothing if the skill doesn't exist.
	 */
	private function snapshot_revision( string $name ): void {
		global $wpdb;

		$skill = $this->find( $name );
		if ( null === $skill ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			Schema::revisions_table_name(),
			[
				'skill_name'     => $name,
				'body'           => (string) $skill['body'],
				'description'    => (string) $skill['description'],
				'enable_agentic' => (bool) $skill['enable_agentic'] ? 1 : 0,
				'enable_prompt'  => (bool) $skill['enable_prompt'] ? 1 : 0,
			],
			[ '%s', '%s', '%s', '%d', '%d' ]
		);

		$this->prune_revisions( $name );
	}

	/**
	 * Delete oldest revisions for a skill beyond the $keep cap.
	 */
	private function prune_revisions( string $name, int $keep = 10 ): void {
		global $wpdb;
		$table = Schema::revisions_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE skill_name = %s", $name ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( $count <= $keep ) {
			return;
		}

		$delete_count = $count - $keep;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE skill_name = %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$name,
				$delete_count
			)
		);
	}
}
