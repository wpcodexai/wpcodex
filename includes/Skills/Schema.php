<?php
/**
 * Skills — database schema.
 *
 * @package WPCodex
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
	public const TABLE_VERSION        = 2;

	/**
	 * Create or upgrade the skills and revisions tables. Safe to call on every activation.
	 */
	public static function create_table(): void {
		global $wpdb;

		$skills_table    = $wpdb->prefix . 'wpcodex_skills';
		$revisions_table = $wpdb->prefix . 'wpcodex_skill_revisions';
		$charset         = $wpdb->get_charset_collate();

		$sql_skills = "CREATE TABLE {$skills_table} (
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

		$sql_revisions = "CREATE TABLE {$revisions_table} (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			skill_name       VARCHAR(200)        NOT NULL,
			body             LONGTEXT            NOT NULL DEFAULT '',
			description      TEXT                NOT NULL DEFAULT '',
			enable_agentic   TINYINT(1)          NOT NULL DEFAULT 1,
			enable_prompt    TINYINT(1)          NOT NULL DEFAULT 1,
			created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY skill_name (skill_name)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_skills );
		dbDelta( $sql_revisions );

		update_option( self::TABLE_VERSION_OPTION, self::TABLE_VERSION, false );
	}

	/**
	 * Run create_table() if the stored version is behind TABLE_VERSION.
	 * Called on plugins_loaded so existing installs get the revisions table
	 * without needing to re-activate the plugin.
	 */
	public static function maybe_upgrade(): void {
		$current = (int) get_option( self::TABLE_VERSION_OPTION, 0 );
		if ( $current < self::TABLE_VERSION ) {
			self::create_table();
		}
	}

	/**
	 * Return the full skills table name with prefix.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpcodex_skills';
	}

	/**
	 * Return the full revisions table name with prefix.
	 */
	public static function revisions_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpcodex_skill_revisions';
	}
}