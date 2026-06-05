<?php
/**
 * Skills — database schema.
 *
 * @package WPCodex\Skills
 */

declare( strict_types=1 );

namespace WPCodex\Skills;

/**
 * Class Schema
 *
 * Manages the wpcodex_skills custom table.
 */
class Schema {

	public const TABLE_VERSION_OPTION = 'wpcodex_skills_table_version';
	public const TABLE_VERSION        = 1;

	/**
	 * Create or upgrade the skills table. Safe to call on every activation.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table   = $wpdb->prefix . 'wpcodex_skills';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name            VARCHAR(200)        NOT NULL,
			description     TEXT                NOT NULL,
			body            LONGTEXT            NOT NULL,
			enable_agentic  TINYINT(1)          NOT NULL DEFAULT 1,
			enable_prompt   TINYINT(1)          NOT NULL DEFAULT 1,
			created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::TABLE_VERSION_OPTION, self::TABLE_VERSION, false );
	}

	/**
	 * Return the full table name with prefix.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpcodex_skills';
	}
}